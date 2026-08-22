<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceSimpleActionHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $action = RedFoxInput::string($this->data, 'action');
        $username = RedFoxInput::string($this->data, 'username');

        if ($action === '' || $username === '') {
            RedFoxResponse::badRequest('action and username are required');
        }

        $invoice = RedFoxDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            RedFoxResponse::notFound('Service not found');
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (!empty($panel) && function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel((array)$panel)) {
            $emergencyMap = nmEmergencyReplacementMap();
            $srcCode = (string)$panel['code_panel'];
            if (isset($emergencyMap['by_code'][$srcCode])) {
                $panel = $emergencyMap['by_code'][$srcCode];
            }
        }
        if (empty($panel)) {
            RedFoxResponse::notFound('Panel not found');
        }

        switch ($action) {
            case 'note':
                $this->handleNote($invoice);
                return;
            case 'toggle_status':
                $this->handleToggleStatus($invoice, $panel);
                return;
            case 'report_problem':
                $this->handleReportProblem($invoice, $panel);
                return;
            case 'update_info':
                RedFoxResponse::ok([
                    'kind'    => 'done',
                    'message' => '✅ اطلاعات بروزرسانی شد',
                ]);
                return;
        }

        RedFoxResponse::badRequest('Unknown simple action: ' . $action);
    }


    private function handleNote(array $invoice): void
    {
        $row = select('setting', '*', null, null, 'select');
        $statusNameCustom = is_array($row) ? (string)($row['statusnamecustom'] ?? '') : '';
        if ($statusNameCustom === 'offnamecustom') {
            RedFoxResponse::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }

        $text = trim(RedFoxInput::string($this->data, 'text'));

        if (mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 200);
        }

        update('invoice', 'note', $text, 'id_invoice', $invoice['id_invoice']);

        RedFoxResponse::ok([
            'kind'    => 'done',
            'message' => $text === '' ? '✅ یادداشت حذف شد' : '✅ یادداشت ذخیره شد',
            'note'    => $text,
        ]);
    }


    private function handleToggleStatus(array $invoice, array $panel): void
    {
        $row = select('shopSetting', '*', 'Namevalue', 'statuschangeservice', 'select');
        $val = is_array($row) ? (string)($row['value'] ?? '') : '';
        if ($val === 'offstatus') {
            RedFoxResponse::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }
        if (($invoice['Status'] ?? '') === 'disablebyadmin') {
            RedFoxResponse::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }

        $managePanel = new ManagePanel();


        $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (!is_array($remote)) {
            RedFoxResponse::fail(502, '❌ ارتباط با پنل برقرار نشد');
        }
        $remoteStatus = (string)($remote['status'] ?? '');
        if ($remoteStatus === 'on_hold') {
            RedFoxResponse::fail(409, '❌ هنوز به کانفیگ متصل نشده اید و امکان تغییر وضعیت سرویس وجود ندارد. بعد از متصل شدن به کانفیگ می توانید از این قابلیت استفاده نمایید.');
        }
        if ($remoteStatus === 'Unsuccessful') {
            RedFoxResponse::fail(502, '❌ خطایی در دریافت وضعیت سرویس از پنل رخ داده است');
        }

        $output = $managePanel->Change_status((string)$invoice['username'], (string)$invoice['Service_location']);
        if (!is_array($output) || (string)($output['status'] ?? '') === 'Unsuccessful') {
            RedFoxResponse::fail(502, '❌ تغییر وضعیت سرویس انجام نشد. لطفاً دوباره تلاش کنید.');
        }


        $remoteAfter = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        $newStatus = is_array($remoteAfter) ? (string)($remoteAfter['status'] ?? '') : '';

        $msg = $newStatus === 'active'
            ? '💡 سرویس روشن شد'
            : '❌ سرویس خاموش شد';

        RedFoxResponse::ok([
            'kind'    => 'done',
            'message' => $msg,
            'status'  => $newStatus,
        ]);
    }


    private function handleReportProblem(array $invoice, array $panel): void
    {
        $row = select('shopSetting', '*', 'Namevalue', 'statusdisorder', 'select');
        $val = is_array($row) ? (string)($row['value'] ?? '') : '';
        if ($val === 'offdisorder') {
            RedFoxResponse::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }

        $userText = trim(RedFoxInput::string($this->data, 'text'));
        if (mb_strlen($userText) > 500) {
            $userText = mb_substr($userText, 0, 500);
        }

        $userId = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $channel = (string)($this->setting['Channel_Report'] ?? '');
        $topicRow = select('topicid', 'idreport', 'report', 'otherservice', 'select');
        $topic = is_array($topicRow) ? (string)($topicRow['idreport'] ?? '') : '';

        $report =
            "⚠️ گزارش اختلال سرویس (از مینی‌اپ)\n\n" .
            "👤 کاربر: <code>{$userId}</code>" . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '') . "\n" .
            "🌍 پنل: " . htmlspecialchars((string)$invoice['Service_location'], ENT_QUOTES) . "\n" .
            "🛍 محصول: " . htmlspecialchars((string)$invoice['name_product'], ENT_QUOTES) . "\n" .
            "👤 نام کاربری سرویس: <code>" . htmlspecialchars((string)$invoice['username'], ENT_QUOTES) . "</code>\n" .
            ($userText !== '' ? "\n📝 توضیحات کاربر:\n" . htmlspecialchars($userText, ENT_QUOTES) . "\n" : '');

        if ($channel !== '') {
            try {
                telegram('sendmessage', [
                    'chat_id'           => $channel,
                    'message_thread_id' => $topic,
                    'text'              => $report,
                    'parse_mode'        => 'HTML',
                ]);
            } catch (Throwable $e) {
                RedFoxLogger::exception($e, (string)('Disorder report send failed'));
            }
        }

        RedFoxResponse::ok([
            'kind'    => 'done',
            'message' => '✅ گزارش اختلال شما برای ادمین ارسال شد',
        ]);
    }
}

