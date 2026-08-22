<?php
declare(strict_types=1);
require_once __DIR__ . '/Portal.php';

function rxp_nav_items(array $ctx): array {
    $items=[
      ['index.php','dashboard','🏠','داشبورد'],
      ['users.php','reports','👥','مشتریان'],
      ['invoices.php','reports','🧾','سفارش‌ها'],
      ['services.php','reports','🔌','سرویس‌ها'],
      ['payments.php','reports','💳','پرداخت‌ها'],
      ['reports.php','reports','📊','گزارش فروش'],
      ['finance.php','reports','💰','گردش مالی'],
      ['settlements.php','profile','🏦','تسویه حساب'],
      ['messages.php','broadcast','📣','پیام‌رسانی'],
      ['tickets.php','support','🎟','تیکت مشتریان'],
      ['api_keys.php','api','🔑','کلیدهای API'],
      ['products.php','products','🛍','محصولات'],
      ['categories.php','categories','📂','دسته‌بندی‌ها'],
      ['cards.php','cards','💳','کارت‌های بانکی'],
      ['branding.php','branding','🎨','برند و متن‌ها'],
      ['button_colors.php','branding','🖌','رنگ دکمه‌ها'],
      ['bot_settings.php','set_prices','🤖','تنظیمات ربات'],
      ['subresellers.php','subresellers','⭐','زیرنمایندگان'],
      ['audit.php','reports','🛡','لاگ فعالیت'],
      ['profile.php','profile','⚙️','حساب کاربری'],
    ];
    return array_values(array_filter($items,fn($x)=>rxp_can($ctx,$x[1])));
}

function rxp_layout_start(array $ctx,string $title,string $active=''): void {
    $name=$ctx['user']['username']?:($ctx['user']['namecustom']?:$ctx['id']);
    $role=$ctx['role']==='super'?'سوپر نماینده':'نماینده';
    $nav=rxp_nav_items($ctx);
    ?><!doctype html><html lang="fa" dir="rtl" data-theme="dark" data-color="blue"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=rxp_h($title)?> | پنل نمایندگی Red Fox</title>
<link rel="stylesheet" href="../panel/css/theme.css">
<style>
.rxp-shell{min-height:100vh}.rxp-side{position:fixed;right:0;top:0;bottom:0;width:250px;background:var(--surface-1);border-left:1px solid var(--border-soft);padding:16px 12px;overflow:auto;z-index:20}.rxp-brand{display:flex;gap:10px;align-items:center;padding:6px 8px 18px;color:var(--text-main)}.rxp-brand img{width:38px;height:38px;border-radius:10px;object-fit:cover}.rxp-brand small{display:block;color:var(--text-muted);font-size:11px;margin-top:3px}.rxp-nav a{display:flex;gap:9px;align-items:center;padding:10px 12px;border-radius:9px;color:var(--text-muted);text-decoration:none;font-size:13px;margin:2px 0}.rxp-nav a:hover,.rxp-nav a.active{background:var(--accent-soft);color:var(--text-main)}.rxp-nav a.active{color:var(--accent);font-weight:700}.rxp-main{margin-right:250px;min-height:100vh}.rxp-top{height:64px;border-bottom:1px solid var(--border-soft);background:var(--surface-1);display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:sticky;top:0;z-index:10}.rxp-content{padding:22px;max-width:1500px;margin:auto}.rxp-title{font-size:clamp(19px,2vw,23px);line-height:1.5;font-weight:800;color:var(--text-main);margin:0}.rxp-sub{font-size:12px;color:var(--text-muted);margin-top:4px}.rxp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:16px 0}.rxp-stat{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:16px}.rxp-stat b{font-size:23px;color:var(--text-main);display:block}.rxp-stat span{font-size:11px;color:var(--text-muted)}.rxp-card{background:var(--surface-1);border:1px solid var(--border-soft);border-radius:14px;padding:17px;margin:14px 0}.rxp-card h2{font-size:clamp(15px,1.4vw,17px);line-height:1.7;color:var(--text-main);margin:0 0 14px}.rxp-table{width:100%;border-collapse:collapse}.rxp-table th,.rxp-table td{padding:10px 8px;border-bottom:1px solid var(--border-soft);font-size:12px;text-align:right;color:var(--text-main)}.rxp-table th{color:var(--text-muted);font-weight:600}.rxp-table-wrap{overflow:auto}.rxp-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.rxp-field label{display:block;color:var(--text-muted);font-size:11px;margin:0 0 4px}.rxp-field input,.rxp-field select,.rxp-field textarea{font-size:13px;line-height:1.8;padding:9px 10px;border:1px solid var(--border-mid);border-radius:8px;background:var(--surface-2,var(--surface-1));color:var(--text-main);font-family:inherit;box-sizing:border-box}.rxp-btn{display:inline-block;border:0;border-radius:8px;padding:8px 13px;background:var(--accent);color:var(--accent-fg);font:600 12px inherit;text-decoration:none;cursor:pointer}.rxp-btn.gray{background:var(--surface-2);color:var(--text-main)}.rxp-btn.red{background:var(--color-danger);color:white}.rxp-btn.green{background:var(--color-success);color:white}.rxp-badge{display:inline-block;padding:3px 8px;border-radius:12px;background:var(--accent-soft);color:var(--accent);font-size:10px}.rxp-flash{padding:11px 14px;border-radius:9px;margin:12px 0;font-size:12px}.rxp-flash.ok{background:var(--color-success-soft);color:var(--color-success)}.rxp-flash.err{background:var(--color-danger-soft);color:var(--color-danger)}.rxp-muted{color:var(--text-muted);font-size:12px}.rxp-pager{display:flex;gap:5px;flex-wrap:wrap;margin-top:12px}.rxp-pager a{padding:6px 10px;border:1px solid var(--border-mid);border-radius:7px;color:var(--text-main);text-decoration:none;font-size:11px}.rxp-mobile{display:none}.rxp-logout{color:var(--color-danger);text-decoration:none;font-size:12px}
@media(max-width:850px){.rxp-side{transform:translateX(105%);transition:.2s}.rxp-side.open{transform:none}.rxp-main{margin-right:0}.rxp-mobile{display:inline-block}.rxp-content{padding:14px}.rxp-top{padding:0 14px}}
</style><script>function rxpMenu(){document.querySelector('.rxp-side').classList.toggle('open')}</script></head><body><div class="rxp-shell">
<aside class="rxp-side"><div class="rxp-brand"><img src="../panel/img/redfox.png" alt=""><div><b>Red Fox</b><small><?=rxp_h($role)?> — <?=rxp_h($name)?></small></div></div><nav class="rxp-nav">
<?php foreach($nav as $n):?><a href="<?=rxp_h($n[0])?>" class="<?=$active===$n[0]?'active':''?>"><span><?=$n[2]?></span><span><?=rxp_h($n[3])?></span></a><?php endforeach;?>
</nav></aside><main class="rxp-main"><header class="rxp-top"><div><button class="rxp-btn gray rxp-mobile" onclick="rxpMenu()">☰</button> <b style="color:var(--text-main)"><?=rxp_h($title)?></b></div><div><span class="rxp-badge"><?=rxp_h($role)?></span> &nbsp; <form method="post" action="logout.php" style="display:inline"><?=redfox_csrf_field()?><button class="rxp-logout" style="border:0;background:none;cursor:pointer">خروج</button></form></div></header><section class="rxp-content">
<?php }
function rxp_layout_end(): void { echo '</section></main></div><script src="../panel/js/persian-date.js" defer></script></body></html>'; }
function rxp_flash(string $msg,string $type='ok'): void { if($msg!=='') echo '<div class="rxp-flash '.($type==='err'?'err':'ok').'">'.rxp_h($msg).'</div>'; }
function rxp_pager(int $page,int $pages,array $query=[]): void { if($pages<=1)return; echo '<div class="rxp-pager">'; for($i=max(1,$page-3);$i<=min($pages,$page+3);$i++){ $q=http_build_query(array_merge($query,['p'=>$i])); echo '<a href="?'.rxp_h($q).'"'.($i===$page?' style="background:var(--accent);color:var(--accent-fg)"':'').'>'.$i.'</a>'; } echo '</div>'; }
