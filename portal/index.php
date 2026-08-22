<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/lib/Portal.php';
require_once __DIR__.'/lib/Layout.php';
$ctx=rxp_context($pdo);
[$scope,$params]=rxp_invoice_scope($ctx,'i');
$done="'active','end_of_time','end_of_volume','sendedwarn','send_on_hold'";
function rxp_scalar(PDO $pdo,string $sql,array $p=[]){try{$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn()?:0;}catch(Throwable $e){return 0;}}
$sales=(int)rxp_scalar($pdo,"SELECT COUNT(*) FROM invoice i WHERE $scope",$params);
$active=(int)rxp_scalar($pdo,"SELECT COUNT(*) FROM invoice i WHERE $scope AND i.Status='active'",$params);
$revenue=(int)rxp_scalar($pdo,"SELECT COALESCE(SUM(CAST(i.price_product AS UNSIGNED)),0) FROM invoice i WHERE $scope AND i.Status IN ($done)",$params);
[$custScope,$custParams]=rxp_customer_scope($ctx,'u');
$customers=(int)rxp_scalar($pdo,"SELECT COUNT(*) FROM user u WHERE $custScope",$custParams);
$pending=(int)rxp_scalar($pdo,"SELECT COUNT(*) FROM invoice i WHERE $scope AND i.Status IN ('unpaid','Unpaid','pending')",$params);
$childCount=max(0,count($ctx['scope_ids'])-1);
$bot=rxp_bot_info($pdo,$ctx['id']);
$recent=[];try{$s=$pdo->prepare("SELECT id_invoice,id_user,name_product,price_product,Status,time_sell FROM invoice i WHERE $scope ORDER BY CAST(time_sell AS UNSIGNED) DESC LIMIT 10");$s->execute($params);$recent=$s->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
rxp_layout_start($ctx,'داشبورد نمایندگی','index.php');
?>
<div><h1 class="rxp-title">داشبورد</h1><div class="rxp-sub">آمار فقط از فروش و مشتریان محدوده نمایندگی شما محاسبه شده است.</div></div>
<div class="rxp-grid">
<div class="rxp-stat"><b><?=rxp_money($ctx['user']['Balance']??0)?></b><span>موجودی کیف پول (تومان)</span></div>
<div class="rxp-stat"><b><?=rxp_money($revenue)?></b><span>فروش موفق</span></div>
<div class="rxp-stat"><b><?=$sales?></b><span>کل سفارش‌ها</span></div>
<div class="rxp-stat"><b><?=$active?></b><span>سرویس فعال</span></div>
<div class="rxp-stat"><b><?=$customers?></b><span>مشتری یکتا</span></div>
<div class="rxp-stat"><b><?=$pending?></b><span>سفارش معلق</span></div>
<?php if($ctx['role']==='super'):?><div class="rxp-stat"><b><?=$childCount?></b><span>زیرنماینده مستقیم</span></div><?php endif;?>
<div class="rxp-stat"><b><?=$bot?'فعال':'ندارد'?></b><span>ربات اختصاصی</span></div>
</div>
<div class="rxp-card"><h2>آخرین سفارش‌ها</h2><div class="rxp-table-wrap"><table class="rxp-table"><thead><tr><th>کد</th><th>مشتری</th><th>محصول</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody>
<?php foreach($recent as $r):?><tr><td><a href="services.php?id=<?=urlencode((string)$r['id_invoice'])?>"><code><?=rxp_h($r['id_invoice'])?></code></a></td><td><?=rxp_h($r['id_user'])?></td><td><?=rxp_h($r['name_product'])?></td><td><?=rxp_money($r['price_product'])?></td><td><span class="rxp-badge"><?=rxp_h($r['Status'])?></span></td></tr><?php endforeach;?>
<?php if(!$recent):?><tr><td colspan="5" class="rxp-muted">سفارشی در این محدوده ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></div>
<?php rxp_layout_end(); ?>
