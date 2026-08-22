<?php
declare(strict_types=1);
final class RedFoxAdminNotifications{
 private PDO$pdo;public function __construct(PDO$pdo){$this->pdo=$pdo;}
 public function sync():void{$sources=[
  ['receipt',"SELECT id_order entity_id,CONCAT('رسید یا پرداخت منتظر بررسی — ',id_order) title,CONCAT('کاربر: ',id_user,' | مبلغ: ',price) body,'payment.php' url,UNIX_TIMESTAMP() ts FROM Payment_report WHERE payment_Status IN ('waiting','pending') ORDER BY id DESC LIMIT 300"],
  ['agent_request',"SELECT id entity_id,CONCAT('درخواست نمایندگی جدید — ',id) title,CONCAT('کاربر: ',username) body,'agents.php' url,UNIX_TIMESTAMP() ts FROM Requestagent WHERE status='waiting' LIMIT 300"],
  ['support',"SELECT Tracking entity_id,CONCAT('پیام پشتیبانی جدید — ',Tracking) title,LEFT(text,500) body,'support_inbox.php' url,UNIX_TIMESTAMP() ts FROM support_message WHERE status='Unseen' ORDER BY time DESC LIMIT 300"],
  ['cancel_service',"SELECT CAST(id AS CHAR) entity_id,CONCAT('درخواست حذف سرویس — ',username) title,LEFT(description,500) body,'cancelService.php' url,UNIX_TIMESTAMP() ts FROM cancel_service WHERE status='waiting' ORDER BY id DESC LIMIT 300"],
  ['deposit',"SELECT request_id entity_id,CONCAT('واریز نماینده — ',reseller_id) title,CONCAT('مبلغ: ',amount,' | پیگیری: ',payment_reference) body,'reseller_settlements.php' url,created_at ts FROM reseller_deposit_requests WHERE status='pending' ORDER BY created_at DESC LIMIT 300"],
  ['payment_reconcile',"SELECT order_id entity_id,CONCAT('پرداخت نیازمند تطبیق — ',order_id) title,LEFT(IFNULL(last_error,''),500) body,'payment_effects.php' url,updated_at ts FROM payment_effects WHERE status='needs_reconcile' LIMIT 300"],
  ['service_reconcile',"SELECT operation_id entity_id,CONCAT('سرویس نیازمند تطبیق — ',operation_id) title,LEFT(IFNULL(result,''),500) body,'service_operations.php' url,updated_at ts FROM reseller_service_operations WHERE status='needs_reconcile' LIMIT 300"],
  ['message_reconcile',"SELECT CAST(id AS CHAR) entity_id,CONCAT('پیام نیازمند تطبیق — ',id) title,LEFT(IFNULL(last_error,''),500) body,'reseller_security.php?tab=reconcile' url,UNIX_TIMESTAMP() ts FROM reseller_message_queue WHERE status='needs_reconcile' LIMIT 300"],
 ];$ins=$this->pdo->prepare('INSERT INTO admin_notifications(type,title,body,entity_id,url,is_read,created_at) VALUES(?,?,?,?,?,0,?) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body),url=VALUES(url)');foreach($sources as[$type,$sql]){try{$rows=$this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);foreach($rows as$r){$id=trim((string)($r['entity_id']??''));if($id==='')continue;$ins->execute([$type,(string)$r['title'],(string)($r['body']??''),$id,(string)$r['url'],(int)($r['ts']?:time())]);}}catch(Throwable$e){/* optional source/table */}}}
 public function unread(int$limit=20):array{$q=$this->pdo->prepare('SELECT * FROM admin_notifications WHERE is_read=0 ORDER BY created_at DESC,id DESC LIMIT '.max(1,min(100,$limit)));$q->execute();return$q->fetchAll(PDO::FETCH_ASSOC);}
 public function count():int{return(int)$this->pdo->query('SELECT COUNT(*) FROM admin_notifications WHERE is_read=0')->fetchColumn();}
 public function counts():array{$rows=$this->pdo->query('SELECT type,COUNT(*) c FROM admin_notifications WHERE is_read=0 GROUP BY type')->fetchAll(PDO::FETCH_ASSOC);$o=[];foreach($rows as$r)$o[$r['type']]=(int)$r['c'];return$o;}
 public function mark(int$id):void{$this->pdo->prepare('UPDATE admin_notifications SET is_read=1 WHERE id=?')->execute([$id]);}
 public function markAll():void{$this->pdo->exec('UPDATE admin_notifications SET is_read=1 WHERE is_read=0');}
 public function markTypes(array$types):void{if(!$types)return;$types=array_values(array_filter($types,fn($x)=>preg_match('/^[a-z_]+$/',$x)));if(!$types)return;$ph=implode(',',array_fill(0,count($types),'?'));$this->pdo->prepare("UPDATE admin_notifications SET is_read=1 WHERE type IN ($ph)")->execute($types);}
}
