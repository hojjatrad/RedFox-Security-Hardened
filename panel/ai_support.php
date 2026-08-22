<?php
/**
 * Red Fox — پشتیبانی هوش مصنوعی (فاز ۱): تنظیمات + لاگ مکالمات.
 * تنظیمات به‌صورت JSON در setting.ai_support ذخیره می‌شود.
 */
if (!defined('REDFOX_SKIP_BOTAPI_ROUTER')) define('REDFOX_SKIP_BOTAPI_ROUTER', true);
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';

$adminRow = null;
if (!empty($_SESSION['user'])) {
    $q = $pdo->prepare("SELECT * FROM admin WHERE username = :u LIMIT 1");
    $q->bindValue(':u', $_SESSION['user'], PDO::PARAM_STR); $q->execute();
    $adminRow = $q->fetch(PDO::FETCH_ASSOC);
}
if (!$adminRow) { header('Location: login.php'); exit; }
$isMainAdmin = (isset($adminRow['rule']) && $adminRow['rule'] === 'administrator');
$flash = ''; $flashType = 'info';

rx_require_schema($pdo, [], ['setting'=>['ai_support']]);

// دیباگ: بررسی وضعیت ذخیره‌سازی
$rxAiDebug = '';
try {
    $rxRaw = (string)$pdo->query("SELECT ai_support FROM setting LIMIT 1")->fetchColumn();
    $rxDec = json_decode($rxRaw, true);
    if (is_array($rxDec)) {
        $rxAiDebug = "status=" . ($rxDec['status'] ?? '?') . " key=" . (!empty($rxDec['api_key']) ? 'SET' : 'EMPTY') . " model=" . ($rxDec['model'] ?? '?');
    } else {
        $rxAiDebug = "ai_support is empty/null (raw=" . substr($rxRaw, 0, 50) . ")";
    }
} catch (Throwable $e) {
    $rxAiDebug = "ERROR: " . redfox_public_exception($e, 'panel/ai_support.php');
}

// Red Fox: لیست ارائه‌دهنده‌ها
function redfox_ai_presets() {
    return [
        'openrouter_free' => [
            'name' => '🆓 OpenRouter (رایگان — پیشنهادی)',
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'model' => 'openrouter/free',
            'models' => ['openrouter/free','meta-llama/llama-3.3-70b-instruct:free','qwen/qwen3-coder:free','nvidia/nemotron-3-super:free','openai/gpt-oss-120b:free'],
            'key_url' => 'https://openrouter.ai/keys',
            'note' => 'مدل openrouter/free خودکار بهترین مدل رایگانِ موجود را انتخاب می‌کند',
        ],
        'groq' => [
            'name' => '⚡ Groq (سریع و رایگان — پایدار)',
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model' => 'llama-3.3-70b-versatile',
            'models' => ['llama-3.3-70b-versatile','llama-3.1-8b-instant','gemma2-9b-it','llama-guard-3-8b'],
            'key_url' => 'https://console.groq.com/keys',
            'note' => 'بسیار سریع — کلید رایگان از console.groq.com/keys',
        ],
        'openai' => [
            'name' => '🟢 OpenAI (GPT)',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'models' => ['gpt-4o-mini','gpt-4o','gpt-3.5-turbo'],
            'key_url' => 'https://platform.openai.com/api-keys',
            'note' => 'پولی — از platform.openai.com',
        ],
        'deepseek' => [
            'name' => '🔵 DeepSeek (ارزان)',
            'url' => 'https://api.deepseek.com/v1/chat/completions',
            'model' => 'deepseek-chat',
            'models' => ['deepseek-chat','deepseek-reasoner'],
            'key_url' => 'https://platform.deepseek.com',
            'note' => 'ارزان — از platform.deepseek.com',
        ],
        'together' => [
            'name' => '🟣 Together AI',
            'url' => 'https://api.together.xyz/v1/chat/completions',
            'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            'models' => ['meta-llama/Llama-3.3-70B-Instruct-Turbo','meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo','Qwen/Qwen2.5-7B-Instruct-Turbo'],
            'key_url' => 'https://api.together.xyz/settings/api-keys',
            'note' => 'از api.together.xyz',
        ],
        'custom' => [
            'name' => '⚙️ سفارشی (OpenAI-compatible)',
            'url' => '',
            'model' => '',
            'models' => [],
            'key_url' => '',
            'note' => 'آدرس و مدل را دستی وارد کنید',
        ],
    ];
}

// پیش‌فرض‌ها
$defaults = [
    'status' => 'off',
    'api_url' => 'https://api.openai.com/v1/chat/completions',
    'api_key' => '',
    'model' => 'gpt-4o-mini',
    'system_prompt' => "تو دستیار پشتیبانی یک فروشگاه اشتراک VPN هستی و به فارسی محترمانه و کوتاه پاسخ می‌دهی.\nقوانین:\n۱) فقط سؤالات عمومی (نحوه‌ی اتصال، تفاوت پلن‌ها، روش پرداخت، سؤالات متداول) را جواب بده.\n۲) اگر سؤال مربوط به حسابِ شخصی کاربر (موجودی، وضعیت سرویس، رسید، بازگشت وجه، شارژ) است یا مطمئن نیستی، در ابتدای پاسخ دقیقاً بنویس «[ESCALATE]» و سپس فقط یک جمله کوتاه بنویس که کاربر را به ادمین ارجاع می‌دهی.\n۳) در غیر این صورت [ESCALATE] را هرگز ننویس.\n۴) هرگز مبلغ یا قیمتی را اگر مطمئن نیستی نگو.\n۵) هرگز قول بازگشت وجه یا تغییر حساب نده.",
    'escalate_keywords' => "ادمین,انسانی,اپراتور,بشر,شارژ کن,موجودی,رسید,بازگشت وجه,پرداخت",
    'max_history' => 4,
    'time_window' => 6,
    'confidence_escalation' => 'on',
];

function redfox_ai_cfg_load() {
    global $pdo, $defaults;
    try {
        $s = $pdo->prepare("SELECT ai_support AS v FROM setting LIMIT 1"); $s->execute();
        $raw = $s->fetchColumn();
        $dec = json_decode((string)$raw, true);
        if (is_array($dec)) return array_merge($defaults, $dec);
    } catch (Throwable $e) {}
    return $defaults;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isMainAdmin && (string)($_POST['action'] ?? '') === 'save') {
    try {
        $cfg = [
            'status' => (string)($_POST['status'] ?? 'off') === 'on' ? 'on' : 'off',
            'api_url' => trim((string)($_POST['api_url'] ?? '')),
            'api_key' => trim((string)($_POST['api_key'] ?? '')),
            'model' => trim((string)($_POST['model'] ?? '')),
            'system_prompt' => (string)($_POST['system_prompt'] ?? ''),
            'escalate_keywords' => (string)($_POST['escalate_keywords'] ?? ''),
            'max_history' => max(0, min(10, (int)($_POST['max_history'] ?? 4))),
            'time_window' => max(0, min(168, (int)($_POST['time_window'] ?? 6))),
            'confidence_escalation' => (string)($_POST['confidence_escalation'] ?? 'off') === 'on' ? 'on' : 'off',
        ];
        // اگر provider انتخاب شده، آدرس و مدل را از لیست بگیر
        $rxProvider = (string)($_POST['provider'] ?? '');
        if ($rxProvider !== '' && $rxProvider !== 'custom') {
            $rxPresets = redfox_ai_presets();
            if (isset($rxPresets[$rxProvider])) {
                $cfg['api_url'] = $rxPresets[$rxProvider]['url'];
                if (trim((string)($_POST['model'] ?? '')) === $rxPresets[$rxProvider]['model'] || trim((string)($_POST['model'] ?? '')) === '') {
                    $cfg['model'] = $rxPresets[$rxProvider]['model'];
                }
            }
        }
        $pdo->prepare("UPDATE setting SET ai_support = ?")->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
        $flash = 'تنظیمات هوش مصنوعی ذخیره شد.'; $flashType = 'success';
        $defaults = $cfg;
    } catch (Throwable $e) { $flash = 'خطا: ' . htmlspecialchars(redfox_public_exception($e, 'panel/ai_support.php'), ENT_QUOTES); $flashType = 'error'; error_log('[ai_support.php] '.redfox_exception_fingerprint($e)); }
} else {
    $defaults = redfox_ai_cfg_load();
}
$cfg = $defaults;

// لاگ مکالمات (فقط ۱۰۰ رکورد اخیر)
$logs = [];
try { $logs = $pdo->query("SELECT * FROM ai_support_log ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پشتیبانی هوش مصنوعی | ربات رد فاکس</title><link rel="stylesheet" href="css/theme.css">
<style>
.rx-flash{padding:12px 16px;border-radius:10px;margin:0 0 16px;font-size:13px;line-height:1.9}
.rx-flash.success{background:var(--color-success-soft);color:var(--color-success)}
.rx-flash.error{background:var(--color-danger-soft);color:var(--color-danger)}
.rx-flash.info{background:var(--accent-soft);color:var(--text-main)}
.rx-card-title{color:var(--text-main)}.rx-help{color:var(--text-muted);font-size:12px;line-height:1.9;margin-top:12px}
.rx-field{margin-bottom:12px}.rx-field label{display:block;color:var(--text-muted);font-size:13px;margin-bottom:4px}
.rx-field input,.rx-field textarea,.rx-field select{width:100%;max-width:680px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;font-family:inherit;box-sizing:border-box}
.rx-field textarea{min-height:90px}
.rx-toggle{display:inline-flex;align-items:center;gap:8px}
.rx-toggle input{width:auto}
.rx-msg{border-radius:8px;padding:8px 10px;margin:3px 0;font-size:12px;white-space:pre-wrap;word-break:break-word}
.rx-msg.user{background:var(--color-success-soft);color:var(--text-main)}
.rx-msg.assistant{background:var(--accent-soft);color:var(--text-main)}
.rx-msg.esc{background:var(--color-warning-soft);color:var(--text-main)}
.rx-msg small{display:block;color:var(--text-muted);font-size:10px;margin-top:3px}
code{color:var(--text-main)}
</style></head><body>
<section id="container"><?php include("header.php"); ?>
<section id="main-content"><div class="wrapper">
<div class="page-head"><h1 class="page-head__title">🤖 پشتیبانی هوش مصنوعی</h1>
<div class="page-head__sub">پاسخ‌گویی خودکار به پشتیبانی + ارجاع به ادمین در صورت نیاز</div></div>

<?php if ($flash !== ''): ?><div class="rx-flash <?= htmlspecialchars($flashType,ENT_QUOTES) ?>"><?= $flash ?></div><?php endif; ?>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">⚙️ تنظیمات</h2></div>
<?php if ($isMainAdmin): ?>
<form method="post">
<input type="hidden" name="action" value="save">
<div class="rx-field rx-toggle"><input type="checkbox" name="status" value="on" id="aistat" <?= $cfg['status']==='on'?'checked':'' ?>><label for="aistat" style="margin:0">فعال‌سازی پاسخ‌گویی هوش مصنوعی</label></div>

<div class="rx-field">
<label>🤖 انتخاب ارائه‌دهنده (Provider)</label>
<select name="provider" id="ai_provider" onchange="rxProv()" style="width:100%;max-width:680px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box">
<?php
$rxPresets = redfox_ai_presets();
$rxMatch = 'custom';
foreach ($rxPresets as $rxPk => $rxPv) { if ($cfg['api_url'] === $rxPv['url']) { $rxMatch = $rxPk; break; } }
foreach ($rxPresets as $rxPk => $rxPv): ?>
<option value="<?= $rxPk ?>" <?= $rxMatch===$rxPk?'selected':'' ?>><?= $rxPv['name'] ?></option>
<?php endforeach; ?>
</select>
<?php $rxCur = $rxPresets[$rxMatch] ?? $rxPresets['custom']; ?>
<p style="font-size:11px;color:var(--text-muted);margin-top:4px" id="ai_prov_note">rxCurNote</p>
</div>

<div class="rx-field"><label>🔑 کلید API</label><input type="text" name="api_key" value="<?= htmlspecialchars($cfg['api_key'],ENT_QUOTES,'UTF-8') ?>" placeholder="sk-..." style="direction:ltr"></div>
<div class="rx-field"><label>🌐 آدرس API (خودکار پر می‌شود)</label><input type="text" name="api_url" value="<?= htmlspecialchars($cfg['api_url'],ENT_QUOTES,'UTF-8') ?>" placeholder="https://..." style="direction:ltr" id="ai_url"></div>
<div class="rx-field">
<label>📦 مدل</label>
<input type="hidden" name="model" id="ai_model_hidden" value="<?= htmlspecialchars($cfg['model'],ENT_QUOTES,'UTF-8') ?>">
<select id="ai_model_sel" style="display:block;width:100%;max-width:680px;padding:9px 11px;border-radius:8px;border:1px solid var(--border-mid);background:var(--surface-1);color:var(--text-main);font-size:13px;box-sizing:border-box;margin-bottom:4px" onchange="document.getElementById('ai_model_hidden').value=this.value">
<?php foreach (($rxCur['models'] ?? []) as $rxM): ?>
<option value="<?= htmlspecialchars($rxM) ?>" <?= $cfg['model']===$rxM?'selected':'' ?>><?= htmlspecialchars($rxM) ?></option>
<?php endforeach; ?>
</select>
<input type="text" value="<?= htmlspecialchars($cfg['model'],ENT_QUOTES,'UTF-8') ?>" placeholder="نام مدل (اگر دستی می‌خواهی)" id="ai_model_txt" style="direction:ltr" oninput="document.getElementById('ai_model_hidden').value=this.value">
</div>
<div class="rx-field"><label>دستورالعمل سیستم (System Prompt)</label><textarea name="system_prompt"><?= htmlspecialchars($cfg['system_prompt'],ENT_QUOTES,'UTF-8') ?></textarea></div>
<div class="rx-field"><label>کلمات ارجاع به ادمین (با کاما جدا کنید)</label><input type="text" name="escalate_keywords" value="<?= htmlspecialchars($cfg['escalate_keywords'],ENT_QUOTES,'UTF-8') ?>"></div>
<div class="rx-field"><label>تعداد پیام‌های اخیر برای حافظه‌ی مکالمه (۰ تا ۱۰)</label><input type="number" name="max_history" value="<?= (int)$cfg['max_history'] ?>" min="0" max="10" style="max-width:120px"></div>
<div class="rx-field"><label>پنجره‌ی زمانی حافظه (ساعت — فقط پیام‌های این مدت اخیر به‌خاطر سپرده شود؛ ۰ = بدون محدودیت)</label><input type="number" name="time_window" value="<?= (int)$cfg['time_window'] ?>" min="0" max="168" style="max-width:120px"></div>
<div class="rx-field rx-toggle"><input type="checkbox" name="confidence_escalation" value="on" id="aiconf" <?= ($cfg['confidence_escalation'] ?? 'on')==='on'?'checked':'' ?>><label for="aiconf" style="margin:0">ارجاع هوشمند: اگر هوش مصنوعی در پاسخ نشانگر [ESCALATE] گذاشت، خودکار به ادمین ارجاع شود</label></div>
<button class="btn btn-sm btn-primary" type="submit">ذخیره تنظیمات</button>
</form>
<?php else: ?><p class="text-muted">فقط ادمین اصلی می‌تواند تنظیم کند.</p><?php endif; ?>
<p class="rx-info">
💡 این رابط با <b>OpenAI، Gemini، Claude، Grok، Qwen، Kimi، OpenRouter و Ollama (لوکال)</b> کار می‌کند — فقط آدرس/کلید/مدل را مطابق مستندات provider تنظیم کن.<br>
🚦 <b>ارجاع خودکار به ادمین</b> وقتی رخ می‌دهد که: کاربر یکی از «کلمات ارجاع» را بفرستد، یا روی دکمه‌ی «ارجاع به ادمین» بزند، یا هوش مصنوعی نتواند جواب دهد.<br>
🔧 <b>دیباگ وضعیت فعلی:</b> <code><?= htmlspecialchars($rxAiDebug, ENT_QUOTES, 'UTF-8') ?></code><br>
⚠️ هوش مصنوعی فقط در پیام‌های <b>پشتیبانی از داخل ربات تلگرام</b> (از طریق دکمه‌ی پشتیبانی) فعال است — نه از مینی‌اپ.
</p></div>

<div class="card"><div class="card__head"><h2 class="card__title rx-card-title">📜 لاگ مکالمات اخیر</h2></div>
<?php if (empty($logs)): ?><p class="text-muted">هنوز مکالمه‌ای ثبت نشده است.</p><?php else: ?>
<div style="max-height:520px;overflow:auto">
<?php foreach ($logs as $lg):
    $role = (string)$lg['role']; $esc = (int)$lg['escalated'];
    $cls = $esc ? 'esc' : ($role === 'user' ? 'user' : 'assistant');
    $lbl = $esc ? '🔁 ارجاع' : ($role === 'user' ? '👤 کاربر' : '🤖 هوش مصنوعی');
?>
<div class="rx-msg <?= $cls ?>"><b><?= $lbl ?></b> — کاربر <code><?= htmlspecialchars((string)$lg['id_user'],ENT_QUOTES,'UTF-8') ?></code><br><?= nl2br(htmlspecialchars((string)$lg['message'],ENT_QUOTES,'UTF-8')) ?>
<small><?= htmlspecialchars((string)$lg['created_at'],ENT_QUOTES,'UTF-8') ?></small></div>
<?php endforeach; ?>
</div><?php endif; ?></div>

</div></section></section>
<script>
var RX_PRESETS = <?php
$rxJs = [];
foreach (redfox_ai_presets() as $k => $v) {
    $rxJs[$k] = ['url'=>$v['url'], 'model'=>$v['model'], 'models'=>$v['models'] ?? [], 'note'=>$v['note'] ?? '', 'key_url'=>$v['key_url'] ?? ''];
}
echo json_encode($rxJs, JSON_UNESCAPED_UNICODE);
?>;

function rxProv() {
    var sel = document.getElementById('ai_provider');
    var key = sel.value;
    var data = RX_PRESETS[key] || RX_PRESETS['custom'];

    // set URL
    if (data.url) document.getElementById('ai_url').value = data.url;

    // set model select
    var modelSel = document.getElementById('ai_model_sel');
    var modelHidden = document.getElementById('ai_model_hidden');
    var modelTxt = document.getElementById('ai_model_txt');
    modelSel.innerHTML = '';
    if (data.models && data.models.length > 0) {
        data.models.forEach(function(m) {
            var opt = document.createElement('option');
            opt.value = m; opt.textContent = m;
            modelSel.appendChild(opt);
        });
        modelSel.value = data.models[0];
        modelHidden.value = data.models[0];
        modelTxt.value = data.models[0];
        modelSel.style.display = 'block';
    } else {
        modelSel.style.display = 'none';
        modelHidden.value = '';
        modelTxt.value = '';
    }

    // note
    var noteEl = document.getElementById('ai_prov_note');
    var noteText = data.note || '';
    if (data.key_url) noteText += (noteText ? ' \u2014 ' : '') + '\u06a9\u0644\u06cc\u062f: ' + data.key_url;
    noteEl.innerHTML = noteText ? ('\uD83D\uDCA1 ' + noteText) : '';
}
</script>

</body></html>
