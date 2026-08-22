<?php
/**
 * Transactional/idempotent service operations initiated by reseller portal.
 * Funds are reserved before the remote panel call and compensated on failure.
 */
declare(strict_types=1);

final class ResellerServiceOperations
{
    private PDO $pdo;
    private array $ctx;

    public function __construct(PDO $pdo, array $ctx) { $this->pdo=$pdo; $this->ctx=$ctx; }

    public function quote(array $invoice, string $action, int $amount=0, string $productCode=''): array
    {
        $owner=$this->ownerForInvoice($invoice);
        $panel=$this->panel($invoice);
        $agentType=$this->agentType($owner);
        if (($panel['status_extend']??'')==='off_extend') throw new RuntimeException('عملیات تمدید در این پنل غیرفعال است.');
        if ($action==='extra_volume' || $action==='extra_time') {
            if ($amount<1 || $amount>10000) throw new RuntimeException('مقدار خارج از محدوده مجاز است.');
            $field=$action==='extra_volume'?'priceextravolume':'priceextratime';
            $unit=$this->priceValue($panel[$field]??0,$agentType);
            if ($unit<0) throw new RuntimeException('قیمت پنل نامعتبر است.');
            return ['owner'=>$owner,'action'=>$action,'amount'=>$amount,'unit_price'=>$unit,'price'=>$unit*$amount,'panel'=>$panel,'product'=>null];
        }
        if ($action==='renew') {
            if ($productCode==='') throw new RuntimeException('محصول تمدید انتخاب نشده است.');
            $q=$this->pdo->prepare("SELECT * FROM product WHERE code_product=? AND (agent=? OR agent=? OR agent='all') LIMIT 1");
            $q->execute([$productCode,$agentType,$owner]);$product=$q->fetch(PDO::FETCH_ASSOC);
            if(!$product) throw new RuntimeException('محصول تمدید برای این نماینده مجاز نیست.');
            $price=(int)$product['price_product'];
            $bot=$this->bot($owner);
            if($bot){$dir=$this->botDir($owner,(string)$bot['username']);$f=$dir?$dir.'/product.json':'';$map=($f&&is_file($f))?(json_decode((string)file_get_contents($f),true)?:[]):[];if(isset($map[$productCode]))$price=max($price,(int)$map[$productCode]);}
            return ['owner'=>$owner,'action'=>$action,'amount'=>1,'unit_price'=>$price,'price'=>$price,'panel'=>$panel,'product'=>$product];
        }
        throw new RuntimeException('عملیات ناشناخته است.');
    }

    public function execute(array $invoice,string $action,int $amount,string $productCode,string $idempotencyKey): array
    {
        if(!preg_match('/^[a-f0-9]{32,64}$/',$idempotencyKey)) throw new RuntimeException('کلید عملیات نامعتبر است. صفحه را تازه‌سازی کنید.');
        $quote=$this->quote($invoice,$action,$amount,$productCode);$owner=$quote['owner'];$price=(int)$quote['price'];
        $this->assertUnambiguousUsername($invoice);
        $opId=bin2hex(random_bytes(16));$now=time();
        try {
            $this->pdo->beginTransaction();
            $ins=$this->pdo->prepare("INSERT INTO reseller_service_operations(operation_id,idempotency_key,actor_reseller_id,owner_reseller_id,invoice_id,action,amount,price,status,result,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?, 'funds_reserved','',?,?)");
            $ins->execute([$opId,$idempotencyKey,$this->ctx['id'],$owner,$invoice['id_invoice'],$action,$amount,$price,$now,$now]);
            if($price>0){$debit=$this->pdo->prepare('UPDATE user SET Balance=Balance-? WHERE id=? AND Balance>=?');$debit->execute([$price,$owner,$price]);if($debit->rowCount()!==1)throw new RuntimeException('موجودی نماینده مالک سرویس کافی نیست.');$this->pdo->prepare('INSERT INTO reseller_wallet_ledger(from_user_id,to_user_id,amount,reason,actor_reseller_id,created_at) VALUES(?,?,?,?,?,?)')->execute([$owner,'system',$price,'service_'.$action,$this->ctx['id'],$now]);}
            $this->pdo->commit();
        } catch(PDOException $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            if((string)$e->getCode()==='23000'){$q=$this->pdo->prepare('SELECT * FROM reseller_service_operations WHERE idempotency_key=? LIMIT 1');$q->execute([$idempotencyKey]);$old=$q->fetch(PDO::FETCH_ASSOC);if($old)return ['ok'=>$old['status']==='succeeded','duplicate'=>true,'operation'=>$old];}
            throw $e;
        } catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}

        $remoteApplied=false;
        try {
            $manage=new ManagePanel();$panel=$quote['panel'];
            if($action==='extra_volume')$remote=$manage->extra_volume((string)$invoice['username'],(string)$panel['code_panel'],$amount);
            elseif($action==='extra_time')$remote=$manage->extra_time((string)$invoice['username'],(string)$panel['code_panel'],$amount);
            else {$p=$quote['product'];$remote=$manage->extend((string)($panel['Methodextend']??''),(int)$p['Volume_constraint'],(int)$p['Service_time'],(string)$invoice['username'],(string)$p['code_product'],(string)$panel['code_panel']);}
            $success=is_array($remote)&&(($remote['status']??false)===true||($remote['status']??'')==='Successful');
            if(!$success)throw new RuntimeException(redfox_remote_error_summary($remote));
            $remoteApplied=true;
            $encoded=json_encode(['status'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $this->pdo->prepare("UPDATE reseller_service_operations SET status='remote_succeeded',result=?,updated_at=? WHERE operation_id=? AND status='funds_reserved'")->execute([$encoded,time(),$opId]);
            $this->finalizeSuccess($opId,$invoice,$quote,$encoded);
            return ['ok'=>true,'duplicate'=>false,'operation_id'=>$opId,'price'=>$price];
        } catch(Throwable $e) {
            $this->compensate($opId,$owner,$price,redfox_exception_fingerprint($e),$remoteApplied);
            return ['ok'=>false,'duplicate'=>false,'operation_id'=>$opId,'price'=>$price,'error'=>redfox_exception_fingerprint($e)];
        }
    }

    private function finalizeSuccess(string $opId,array $invoice,array $quote,string $encoded): void
    {
        $this->pdo->beginTransaction();
        try{$type=['extra_volume'=>'extra_user','extra_time'=>'extra_time_user','renew'=>'extend_user_by_reseller'][$quote['action']];$value=json_encode(['amount'=>$quote['amount'],'product_code'=>$quote['product']['code_product']??null,'operation_id'=>$opId],JSON_UNESCAPED_UNICODE);$q=$this->pdo->prepare('INSERT INTO service_other(id_user,username,value,type,time,price,output,status) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$invoice['id_user'],$invoice['username'],$value,$type,date('Y/m/d H:i:s'),$quote['price'],$encoded,'paid']);$this->pdo->prepare("UPDATE invoice SET Status='active' WHERE id_invoice=?")->execute([$invoice['id_invoice']]);$this->pdo->prepare("UPDATE reseller_service_operations SET status='succeeded',updated_at=? WHERE operation_id=? AND status='remote_succeeded'")->execute([time(),$opId]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function compensate(string $opId,string $owner,int $price,string $reason,bool $remoteApplied=false): void
    {
        try{$this->pdo->beginTransaction();$q=$this->pdo->prepare("SELECT status FROM reseller_service_operations WHERE operation_id=? FOR UPDATE");$q->execute([$opId]);$status=(string)$q->fetchColumn();$newStatus='needs_reconcile';if($status==='funds_reserved'&&!$remoteApplied){$newStatus='refunded';if($price>0){$this->pdo->prepare('UPDATE user SET Balance=Balance+? WHERE id=?')->execute([$price,$owner]);$this->pdo->prepare('INSERT INTO reseller_wallet_ledger(from_user_id,to_user_id,amount,reason,actor_reseller_id,created_at) VALUES(?,?,?,?,?,?)')->execute(['system',$owner,$price,'refund_service_operation',$this->ctx['id'],time()]);}}$this->pdo->prepare("UPDATE reseller_service_operations SET status=?,result=?,updated_at=? WHERE operation_id=? AND status<>'succeeded'")->execute([$newStatus,mb_substr($reason,0,4000),time(),$opId]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();error_log('[service operation compensation] '.$opId.' '.redfox_exception_fingerprint($e));}
    }

    private function ownerForInvoice(array $invoice): string {foreach($this->ctx['scope_ids'] as $id)if((string)($invoice['refral']??'')===(string)$id)return(string)$id;if(!empty($invoice['bottype'])){$q=$this->pdo->prepare('SELECT id_user FROM botsaz WHERE bot_token=? LIMIT 1');$q->execute([(string)$invoice['bottype']]);$id=(string)$q->fetchColumn();if(in_array($id,$this->ctx['scope_ids'],true))return$id;}return$this->ctx['id'];}
    private function panel(array $invoice): array {$q=$this->pdo->prepare('SELECT * FROM marzban_panel WHERE name_panel=? LIMIT 1');$q->execute([$invoice['Service_location']]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p)throw new RuntimeException('پنل سرویس یافت نشد.');return function_exists('rx_secret_decrypt_panel_row')?rx_secret_decrypt_panel_row($p):$p;}
    private function agentType(string $owner): string {$q=$this->pdo->prepare('SELECT agent FROM user WHERE id=?');$q->execute([$owner]);return(string)$q->fetchColumn();}
    private function priceValue($raw,string $agent): int {$d=is_string($raw)?json_decode($raw,true):null;if(is_array($d))return max(0,(int)($d[$agent]??$d['all']??0));return max(0,(int)$raw);}
    private function bot(string $owner): ?array {$q=$this->pdo->prepare('SELECT * FROM botsaz WHERE id_user=? LIMIT 1');$q->execute([$owner]);$r=$q->fetch(PDO::FETCH_ASSOC);return$r?:null;}
    private function botDir(string $owner,string $username): ?string {$root=realpath(__DIR__.'/../vpnbot');$name=preg_replace('/[^A-Za-z0-9_@.-]/','',$username);$real=$root?realpath($root.'/'.$owner.$name):false;return($root&&$real&&strpos($real,$root.'/')===0)?$real:null;}
    private function assertUnambiguousUsername(array $invoice): void {$q=$this->pdo->prepare('SELECT id_invoice,Service_location FROM invoice WHERE username=?');$q->execute([$invoice['username']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $r)if((string)$r['id_invoice']!==(string)$invoice['id_invoice'])throw new RuntimeException('نام کانفیگ در چند فاکتور تکرار شده است؛ عملیات خودکار برای جلوگیری از تغییر سرویس اشتباه متوقف شد.');}
}
