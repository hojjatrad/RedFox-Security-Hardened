<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


$textadmin = ["panel", "/panel", "پنل مدیریت", "ادمین", "👨‍💼 پنل مدیریت"];
if (!in_array($from_id, $admin_idsmain) and !in_array($from_id, $admin_ids)) {
    return;
}
if (in_array($text, $textadmin) || $datain == "admin") {
    $text_admin = "Version Bot : $version
Panel Admin";
    sendmessage($from_id, $text_admin, $keyboardadmin, 'HTML');
    step("home", $from_id);
    return;
}
if ($text == "بازگشت به منوی ادمین") {
    sendmessage($from_id, "به منوی ادمین بازگشتید", $keyboardadmin, 'HTML');
    step("home", $from_id);
    return;
}
if ($text == "📞 تنظیم نام کاربری پشتیبانی") {
    sendmessage($from_id, "📌 نام کاربری جدید خود را بدون @ ارسال کنید", $backadmin, 'HTML');
    step("getusernamesupport", $from_id);
} elseif ($user['step'] == "getusernamesupport") {
    sendmessage($from_id, "✅ نام کاربری پشتیبانی برای شما با موفقیت تنظیم گردید.", $keyboardadmin, 'HTML');
    step("home", $from_id);
    $setting['support_username'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
} elseif ($text == "🔋 قیمت حجم") {
    sendmessage($from_id, "📌 قیمت هر گیگ حجم را ارسال نمایید.
قیمت پایه حجم. : {$setting['minpricevolume']} تومان
قیمت فعلی حجم. : {$setting['pricevolume']} تومان", $backadmin, 'HTML');
    step("getpricvolumeadmin", $from_id);
} elseif ($user['step'] == "getpricvolumeadmin") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) < intval($setting['minpricevolume'])) {
        sendmessage($from_id, "❌ قیمت حجم باید بزرگ تر از قیمت پایه حجم باشد.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ قیمت حجم با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
    step("home", $from_id);
    $setting['pricevolume'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
} elseif ($text == "⌛️ قیمت زمان") {
    sendmessage($from_id, "
📌 قیمت هر روز زمان را ارسال نمایید.
 قیمت پایه زمان. : {$setting['minpricetime']} تومان
قیمت فعلی شما : {$setting['pricetime']} تومان", $backadmin, 'HTML');
    step("getpricvtimeadmin", $from_id);
} elseif ($user['step'] == "getpricvtimeadmin") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    if (intval($text) < intval($setting['minpricetime'])) {
        sendmessage($from_id, "❌ قیمت زمان باید بزرگ تر از قیمت پایه زمان باشد.", $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, "✅ قیمت زمان با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
    step("home", $from_id);
    $setting['pricetime'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
} elseif (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget)) {
    $order_id = $dataget[1];
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [],
            [
                ['text' => "✅ تایید شده", 'callback_data' => "confirmpaid"],
            ]
        ]
    ]);
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($Payment_report['payment_Status'] == "paid" || $Payment_report['payment_Status'] == "reject") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        $textconfrom = "✅. پرداخت توسط ادمین دیگری تایید شده
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
    💸 مبلغ پرداختی: $format_price_cart تومان
";
        Editmessagetext($from_id, $message_id, $textconfrom, $Confirm_pay);
        return;
    }
    DirectPaymentbot($order_id);
    $Payment_report['price'] = number_format($Payment_report['price']);
    $text_report = "📣 نماینده رسیبد پرداخت کارت به کارت را تایید کرد.

اطلاعات :
👤آیدی عددی  ادمین تایید کننده : $from_id
💰 مبلغ پرداخت : {$Payment_report['price']}
👤 ایدی عددی کاربر : <code>{$Payment_report['id_user']}</code>
👤 نام کاربری کاربر : @{$Balance_id['username']}
        کد پیگیری پرداحت : $order_id";
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    update("user", "Processing_value_one", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_tow", "none", "id", $Balance_id['id']);
    update("user", "Processing_value_four", "none", "id", $Balance_id['id']);
} elseif (preg_match('/reject_pay_(\w+)/', $datain, $datagetr)) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => "تراکنش حذف شده است",
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if ($Payment_report['payment_Status'] == "reject" || $Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    update("Payment_report", "payment_Status", "reject", "id_order", $id_order);

    sendmessage($from_id, $textbotlang['Admin']['Payment']['Reasonrejecting'], $backadmin, 'HTML');
    step('reject-dec', $from_id);
    Editmessagetext($from_id, $message_id, $text_inline, null);
} elseif ($user['step'] == "reject-dec") {
    $Payment_report = select("Payment_report", "*", "id_order", $user['Processing_value_one'], "select");
    update("Payment_report", "dec_not_confirmed", $text, "id_order", $user['Processing_value_one']);
    $text_reject = "❌ کاربر گرامی پرداخت شما به دلیل زیر رد گردید.
✍️ $text
🛒 کد پیگیری پرداخت: {$user['Processing_value_one']}
                ";
    sendmessage($from_id, $textbotlang['Admin']['Payment']['Rejected'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $text_reject, null, 'HTML');
    step('home', $from_id);
    $text_report = "❌ یک ادمین رسید پرداخت کارت به کارت را رد کرد.

اطلاعات :
👤آیدی عددی  ادمین تایید کننده : $from_id
نام کاربری ادمین تایید کننده : @$username
💰 مبلغ پرداخت : {$Payment_report['price']}
دلیل رد کردن : $text
👤 ایدی عددی کاربر: {$Payment_report['id_user']}";
    if (strlen($settingmain['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $settingmain['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == "👨‍🔧  مدیریت ادمین ها") {
    $keyboardadmin = ['inline_keyboard' => []];
    foreach ($admin_ids as $admin) {
        $keyboardadmin['inline_keyboard'][] = [
            ['text' => "❌", 'callback_data' => "removeadmin_" . $admin],
            ['text' => $admin, 'callback_data' => "adminlist"],
        ];
    }
    $keyboardadmin['inline_keyboard'][] = [
        ['text' => "👨‍💻 اضافه کردن ادمین", 'callback_data' => "addnewadmin"],
    ];
    $keyboardadmin = json_encode($keyboardadmin);
    sendmessage($from_id, "📌 در بخش زیر می توانید لیست ادمین ها را مشاهده کنید همچنین با زدن دکمه ضربدر می توانید یک ادمین را حذف کنید", $keyboardadmin, 'HTML');
} elseif ($datain == "addnewadmin") {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif ($user['step'] == "addadmin") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['addadminset'], $keyboardadmin, 'HTML');
    sendmessage($user['Processing_value'], $textbotlang['Admin']['manageadmin']['adminedsenduser'], null, 'HTML');
    step('home', $from_id);
    $admin_ids[] = $text;
    update("botsaz", "admin_ids", json_encode($admin_ids), "bot_token", $ApiToken);
} elseif (preg_match('/removeadmin_(\w+)/', $datain, $dataget)) {
    $idadmin = $dataget[1];
    $count = 0;
    foreach ($admin_ids as $admin) {
        if ($admin == $idadmin) {
            unset($admin_ids[$count]);
            break;
        }
        $count += 1;
    }
    unset($admin_ids[$idadmin]);
    $admin_ids = array_values($admin_ids);
    update("botsaz", "admin_ids", json_encode($admin_ids), "bot_token", $ApiToken);
    sendmessage($from_id, "✅ ادمین با موفقیت حذف گردید", null, 'HTML');
} elseif ($text == "🔍 جستجوی کاربر") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetIdUserunblock'], $backadmin, 'HTML');
    step('show_info', $from_id);
} elseif ($user['step'] == "show_info" || strpos($text, "/user ") !== false) {
    if (explode(" ", $text)[0] == "/user") {
        $id_user = explode(" ", $text)[1];
    } else {
        $id_user = $text;
    }
    if (!in_array($id_user, $users_ids)) {
        sendmessage($from_id, $textbotlang['Admin']['not-user'], null, 'HTML');
        return;
    }
    $date = date("Y-m-d");
    $_stmt = $connect->prepare("SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND bottype = ?");
    $_stmt->bind_param("ss", $id_user, $ApiToken); $_stmt->execute();
    $dayListSell = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = ? AND Payment_Method != 'low balance by admin' AND bottype = ?");
    $_stmt->bind_param("ss", $id_user, $ApiToken); $_stmt->execute();
    $balanceall = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND bottype = ?");
    $_stmt->bind_param("ss", $id_user, $ApiToken); $_stmt->execute();
    $subbuyuser = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    $_stmt = $connect->prepare("SELECT count(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND bottype = ?");
    $_stmt->bind_param("ss", $id_user, $ApiToken); $_stmt->execute();
    $invoicecount = $_stmt->get_result()->fetch_assoc()['count(*)']; $_stmt->close();
    if ($invoicecount == 0) {
        $sumvolume['SUM(Volume)'] = 0;
    } else {
        $_stmt = $connect->prepare("SELECT SUM(Volume) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND id_user = ? AND name_product != 'سرویس تست'");
        $_stmt->bind_param("s", $id_user); $_stmt->execute();
        $sumvolume = $_stmt->get_result()->fetch_assoc(); $_stmt->close();
    }
    $user = select("user", "*", "id", $id_user, "select");
    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'],
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'],
    ][$user['roll_Status']];
    if ($subbuyuser['SUM(price_product)'] == null)
        $subbuyuser['SUM(price_product)'] = 0;
    $user['Balance'] = number_format($user['Balance']);
    if ($user['register'] != "none") {
        if ($user['register'] == null)
            return;
        $userjoin = jdate('Y/m/d H:i:s', $user['register']);
    } else {
        $userjoin = "نامشخص";
    }
    if ($user['last_message_time'] == null) {
        $lastmessage = "";
    } else {
        $lastmessage = jdate('Y/m/d H:i:s', $user['last_message_time']);
    }
    $datefirst = time() - 86400;
    $desired_date_time_start = time() - 3600;
    $month_date_time_start = time() - 2592000;
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $listhours = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $desired_date_time_start);
    $stmt->execute();
    $suminvoicehours = $stmt->fetchColumn();
    if ($suminvoicehours == null) {
        $suminvoicehours = "0";
    }
    $sql = "SELECT * FROM invoice WHERE time_sell > :requestedDate AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $listmonth = $stmt->rowCount();
    $sql = "SELECT SUM(price_product) FROM invoice WHERE time_sell > :requestedDate AND (Status = 'active' OR Status = 'end_of_time'  OR Status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND name_product != 'سرویس تست' AND id_user = :id_user AND bottype = '$ApiToken'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_user', $id_user);
    $stmt->bindParam(':requestedDate', $month_date_time_start);
    $stmt->execute();
    $suminvoicemonth = $stmt->fetchColumn();
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "افزایش موجودی", 'callback_data' => 'addbalanceuser_' . $text],
                ['text' => "کم کردن موجودی", 'callback_data' => 'lowbalanceuser_' . $text],
            ],
        ]
    ]);
    $userbalance = number_format(json_decode(file_get_contents("data/$id_user/$id_user.json"), true)['Balance']);
    if ($suminvoicemonth == null) {
        $suminvoicemonth = "0";
    }
    $textinfouser = "👀 اطلاعات کاربر:

🔗 اطلاعات کاربری کاربر

⭕️ وضعیت کاربر : {$user['User_Status']}
⭕️ نام کاربری کاربر : @{$user['username']}
⭕️ آیدی عددی کاربر :  <a href = \"tg://user?id=$id_user\">$id_user</a>
⭕️ زمان عضویت کاربر : $userjoin
⭕️ آخرین زمان  استفاده کاربر از ربات : $lastmessage
⭕️ محدودیت اکانت تست :  {$user['limit_usertest']}
⭕️  مجموع حجم خریداری شده فعال ( برای آمار دقیق حجم باید کرون روشن باشد): {$sumvolume['SUM(Volume)']}

💎 گزارشات مالی

🔰 موجودی کاربر : $userbalance
🔰 تعداد خرید کل کاربر : {$dayListSell['COUNT(*)']}
🔰️ مبلغ کل پرداختی  :  {$balanceall['SUM(price)']}
🔰 جمع کل خرید : {$subbuyuser['SUM(price_product)']}
🔰 تعداد فروش یک ساعت گذشته : $listhours عدد
🔰 مجموع فروش یک ساعت گذشته : $suminvoicehours تومان
🔰 تعداد فروش یک ماه گذشته : $listmonth عدد
🔰 مجموع فروش یک ماه گذشته : $suminvoicemonth تومان
";
    sendmessage($from_id, $textinfouser, $keyboardmanage, 'HTML');
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/addbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanceuserdec'], $backadmin, 'html');
    step('addbalanceusercurrent', $from_id);
} elseif ($user['step'] == "addbalanceusercurrent") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (?,?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "add balance by admin";
    $invoice = null;
    $stmt->bind_param("ssssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice, $ApiToken);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['addbalanced'], $keyboardadmin, 'html');
    $userbalance = json_decode(file_get_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json"), true);
    $Balance_add_user = $userbalance['Balance'] + $text;
    $userbalance['Balance'] = $Balance_add_user;
    file_put_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json", json_encode($userbalance));
    $heibalanceuser = number_format($text, 0);
    $textadd = "💎 کاربر عزیز مبلغ $heibalanceuser تومان به موجودی کیف پول تان اضافه گردید.";
    sendmessage($user['Processing_value'], $textadd, null, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/lowbalanceuser_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['lowbalanceuserdec'], $backadmin, 'html');
    step('addbalanceuser', $from_id);
} elseif ($user['step'] == "addbalanceuser") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    if ($text > 100000000) {
        sendmessage($from_id, "❌ حداکثر مبلغ 100 میلیون تومان می باشد", $backadmin, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice,bottype) VALUES (?,?,?,?,?,?,?,?)");
    $payment_Status = "paid";
    $Payment_Method = "low balance by admin";
    $invoice = null;
    $stmt->bind_param("ssssssss", $user['Processing_value'], $randomString, $dateacc, $text, $payment_Status, $Payment_Method, $invoice, $ApiToken);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['lowbalanced'], $keyboardadmin, 'html');
    $userbalance = json_decode(file_get_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json"), true);
    $Balance_add_user = intval($userbalance['Balance']) - intval($text);
    $userbalance['Balance'] = $Balance_add_user;
    file_put_contents("data/{$user['Processing_value']}/{$user['Processing_value']}.json", json_encode($userbalance));
    $lowbalanceuser = number_format($text, 0);
    $textkam = "❌ کاربر عزیز مبلغ $lowbalanceuser تومان از  موجودی کیف پول تان کسر گردید.";
    sendmessage($user['Processing_value'], $textkam, null, 'HTML');
    step('home', $from_id);
    $statistics = select("user", "*", "bottype", $ApiToken, "count");
    $Balance_user_afters = number_format(select("user", "*", "id", $user['Processing_value'], "select")['Balance']);
} elseif ($text == "📊 آمار ربات") {
    $statistics = select("user", "*", "bottype", $ApiToken, "count");
    $stmt2 = $pdo->prepare("SELECT COUNT( DISTINCT id_user) as count FROM `invoice` WHERE name_product = 'سرویس تست' AND  bottype = '$ApiToken'");
    $stmt2->execute();
    $statisticsorder = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product = 'سرویس تست' AND bottype = '$ApiToken'");
    $stmt->execute();
    $count_usertest = $stmt->rowCount();
    $sql1 = "SELECT COUNT(*) AS invoice_count FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست' AND bottype = '$ApiToken'";
    $stmt1 = $pdo->query($sql1);
    $invoice = $stmt1->fetch(PDO::FETCH_ASSOC)['invoice_count'];
    $sql2 = "SELECT SUM(price_product) AS total_price FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست' AND bottype = '$ApiToken'";
    $stmt2 = $pdo->query($sql2);
    $invoicesum = number_format($stmt2->fetch(PDO::FETCH_ASSOC)['total_price'], 0);
    $statisticsall = "
📊 آمار کلی ربات

📌 تعداد کاربران : $statistics نفر
📌 تعداد کاربرانی که خرید داشتند : $statisticsorder نفر
📌 تعداد اکانت های تست گرفته شده : $count_usertest نفر
📌 تعداد فروش کل : $invoice عدد
📌 جمع فروش کل : $invoicesum تومان
";
    sendmessage($from_id, $statisticsall, null, 'HTML');
} elseif ($text == "💰 تنظیم قیمت محصول") {
    if (!is_file('product.json')) {
        file_put_contents('product.json', "{}");
    }
    $product = [];
    $getdataproduct = mysqli_query($connect, "SELECT * FROM product WHERE agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}'");
    while ($row = mysqli_fetch_assoc($getdataproduct)) {
        $panel = select("marzban_panel", "*", "name_panel", $row['Location'], "select");
        if (in_array($panel['name_panel'], $hide_panel))
            continue;
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => "بازگشت به منوی ادمین"],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
    sendmessage($from_id, "از لیست زیر محصولی که می خواهید قیمت تنظیم نمایید را انتخاب کنید", $json_list_product_list_admin, 'HTML');
    step("selectproductprice", $from_id);
} elseif ($user['step'] == "selectproductprice") {
    $product = select("product", "*", "name_product", $text, "select");
    if ($product == false || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) {
        sendmessage($from_id, "❌ محصول انتخابی وجود ندارد.", null, 'HTML');
        return;
    }
    savedata("clear", "code_product", $product['code_product']);
    step("getpriceproduct", $from_id);
    if (intval($userbot['pricediscount']) != 0) {
        $resultper = ($product['price_product'] * $userbot['pricediscount']) / 100;
        $product['price_product'] = $product['price_product'] - $resultper;
    }
    sendmessage($from_id, "📌  قیمت خود را ارسال کنید
قیمت پایه :{$product['price_product']}", $backadmin, 'HTML');
} elseif ($user['step'] == "getpriceproduct") {
    $userdata = json_decode($user['Processing_value'], true);
    $product = select("product", "*", "code_product", $userdata['code_product'], "select");
    if (!is_array($product) || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) { sendmessage($from_id, "❌ محصول برای این ربات مجاز نیست.", null, 'HTML'); if (function_exists('step')) step('home', $from_id); return; }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['agent']['invalidvlue'], null, 'HTML');
        return;
    }
    if (intval($text) < intval($product['price_product'])) {
        sendmessage($from_id, "❌ قیمت شما کوچیک تر از قیمت پایه است.", null, 'HTML');
        return;
    }
    $productlist = json_decode(file_get_contents('product.json'), true);
    $productlist[$product['code_product']] = intval($text);
    file_put_contents('product.json', json_encode($productlist));
    step("home", $from_id);
    sendmessage($from_id, "✅ قیمت با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
} elseif ($text == "💰 تنظیمات فروشگاه") {
    sendmessage($from_id, "📌 یک گزینه را انتخاب کنید.", $keyboardprice, 'HTML');
} elseif ($text == "⚙️ وضعیت قابلیت ها") {
    $status_custom = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['show_product']];
    $status_note = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['active_step_note']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $status_custom, 'callback_data' => "editstsuts-statusvolume-{$setting['show_product']}"],
                ['text' => "🛍 فروش  حجم دلخواه", 'callback_data' => "statuscustomvolume"],
            ],
            [
                ['text' => $status_note, 'callback_data' => "editstsuts-statusnote-{$setting['active_step_note']}"],
                ['text' => "✏️ یادداشت ", 'callback_data' => "statusnote"],
            ],
            [
                ['text' => ($setting['statuscategorygenral'] ?? 'offcategorys') == 'oncategorys' ? "📂 دسته‌بندی: فعال" : "📂 دسته‌بندی: غیرفعال", 'callback_data' => "editstsuts-statuscategory-" . ($setting['statuscategorygenral'] ?? 'offcategorys')],
            ]
        ]
    ]);
    sendmessage($from_id, "در این بخش می توانید قابلیت های زیر را خاموش یا روشن کنید", $Bot_Status, 'HTML');
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusvolume") {
        if ($value == false) {
            $valuenew = true;
        } else {
            $valuenew = false;
        }
        $setting['show_product'] = $valuenew;
        update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    } elseif ($type == "statusnote") {
        if ($value == false) {
            $valuenew = true;
        } else {
            $valuenew = false;
        }
        $setting['active_step_note'] = $valuenew;
        update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    }
    $dataBase = select("botsaz", "*", "bot_token", $ApiToken, "select");
    $setting = json_decode($dataBase['setting'], true);
    $status_custom = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['show_product']];
    $status_note = [
        '1' => $textbotlang['Admin']['Status']['statuson'],
        '0' => $textbotlang['Admin']['Status']['statusoff']
    ][$setting['active_step_note']];
    $Bot_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['Status']['statussubject'], 'callback_data' => "subjectde"],
                ['text' => $textbotlang['Admin']['Status']['subject'], 'callback_data' => "subject"],
            ],
            [
                ['text' => $status_custom, 'callback_data' => "editstsuts-statusvolume-{$setting['show_product']}"],
                ['text' => "🛍 فروش  حجم دلخواه", 'callback_data' => "statuscustomvolume"],
            ],
            [
                ['text' => $status_note, 'callback_data' => "editstsuts-statusnote-{$setting['active_step_note']}"],
                ['text' => "✏️ یادداشت ", 'callback_data' => "statusnote"],
            ],
            [
                ['text' => ($setting['statuscategorygenral'] ?? 'offcategorys') == 'oncategorys' ? "📂 دسته‌بندی: فعال" : "📂 دسته‌بندی: غیرفعال", 'callback_data' => "editstsuts-statuscategory-" . ($setting['statuscategorygenral'] ?? 'offcategorys')],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, "در این بخش می توانید قابلیت های زیر را خاموش یا روشن کنید", $Bot_Status);
} elseif ($text == "📝 تنظیم متون") {
    sendmessage($from_id, "📌 برای تغییر متن یکی از گزینه های زیر را انتخاب نمایید", $keyboard_change_price, 'HTML');
} elseif ($text == "💎 متن کارت") {
    sendmessage($from_id, "📌 جهت تنظیم متن شماره کارت متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $setting['cart_info'], $backadmin, 'HTML');
    step("getcartinfo", $from_id);
} elseif ($user['step'] == "getcartinfo") {
    sendmessage($from_id, "✅ توضیحات با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $setting['cart_info'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    step("home", $from_id);
} elseif ($text == "🛍 دکمه خرید") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['buy'], $backadmin, 'HTML');
    step("gettext_buy", $from_id);
} elseif ($user['step'] == "gettext_buy") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['buy'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "🔑 دکمه تست") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['test'], $backadmin, 'HTML');
    step("gettext_test", $from_id);
} elseif ($user['step'] == "gettext_test") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['test'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "🛒 دکمه سرویس های من") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['my_service'], $backadmin, 'HTML');
    step("gettext_my_service", $from_id);
} elseif ($user['step'] == "gettext_my_service") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['my_service'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "👤 دکمه حساب کاربری") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['wallet'], $backadmin, 'HTML');
    step("gettext_wallet", $from_id);
} elseif ($user['step'] == "gettext_wallet") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['wallet'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "☎️ متن دکمه پشتیبانی") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['btn_keyboard']['support'], $backadmin, 'HTML');
    step("gettext_support", $from_id);
} elseif ($user['step'] == "gettext_support") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['btn_keyboard']['support'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "💸 متن مرحله افزایش موجودی") {
    sendmessage($from_id, "📌 جهت تنظیم متن جدید را ارسال نمایید. توضیحات فعلی :", $backadmin, 'HTML');
    sendmessage($from_id, $text_bot_var['text_account']['add_balance'], $backadmin, 'HTML');
    step("gettext_add_balance", $from_id);
} elseif ($user['step'] == "gettext_add_balance") {
    sendmessage($from_id, "✅ متن با موفقیت ذخیره گردید.", $keyboard_change_price, 'HTML');
    $text_bot_var['text_account']['add_balance'] = $text;
    file_put_contents('text.json', json_encode($text_bot_var, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    step("home", $from_id);
} elseif ($text == "📣 جوین اجباری") {
    sendmessage($from_id, "📌 کانال خود را جهت تنظیم جوین اجباری ارسال کنید
⚠️ ربات باید ادمین کانال باشد در غیراینصورت این قابلیت فعال نخواهد شد
⚠️ نام کاربری کانال باید بدون @ ارسال شود", $backadmin, 'HTML');
    step("get_channel_id", $from_id);
} elseif ($user['step'] == "get_channel_id") {
    sendmessage($from_id, "✅ کانال با موفقیت ذخیره گردید.", $keyboardadmin, 'HTML');
    $setting['channel'] = $text;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    step("home", $from_id);
} elseif ($text == "✏️ تنظیم نام محصول") {
    if (!is_file('product_name.json')) {
        file_put_contents('product_name.json', "{}");
    }
    $product = [];
    $getdataproduct = mysqli_query($connect, "SELECT * FROM product WHERE agent = '{$userbot['agent']}' OR agent = '{$dataBase['id_user']}'");
    while ($row = mysqli_fetch_assoc($getdataproduct)) {
        $panel = select("marzban_panel", "*", "name_panel", $row['Location'], "select");
        if (in_array($panel['name_panel'], $hide_panel))
            continue;
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_product['keyboard'][] = [
        ['text' => "بازگشت به منوی ادمین"],
    ];
    $json_list_product_list_admin = json_encode($list_product);
    sendmessage($from_id, "از لیست زیر محصولی که می خواهید نام تنظیم نمایید را انتخاب کنید", $json_list_product_list_admin, 'HTML');
    step("get_product_for_edit_name", $from_id);
} elseif ($user['step'] == "get_product_for_edit_name") {
    $product = select("product", "*", "name_product", $text, "select");
    if ($product == false || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) {
        sendmessage($from_id, "❌ محصول انتخابی وجود ندارد.", null, 'HTML');
        return;
    }
    savedata("clear", "code_product", $product['code_product']);
    step("get_new_name", $from_id);
    sendmessage($from_id, "📌  نام خود را ارسال کنید", $backadmin, 'HTML');
} elseif ($user['step'] == "get_new_name") {
    $userdata = json_decode($user['Processing_value'], true);
    $product = select("product", "*", "code_product", $userdata['code_product'], "select");
    if (!is_array($product) || (function_exists('rxVpnbotProductAllowed') && !rxVpnbotProductAllowed($product, $userbot, $dataBase))) { sendmessage($from_id, "❌ محصول برای این ربات مجاز نیست.", null, 'HTML'); if (function_exists('step')) step('home', $from_id); return; }
    $productlist = json_decode(file_get_contents('product_name.json'), true);
    $productlist[$product['code_product']] = $text;
    file_put_contents('product_name.json', json_encode($productlist));
    step("home", $from_id);
    sendmessage($from_id, "✅ نام با موفقیت تنظیم گردید.", $keyboardprice, 'HTML');
}

// ── فعال/غیرفعال‌کردن دسته‌بندی در ربات نماینده ──
if (preg_match('/^editstsuts-statuscategory-(.*)/', $datain, $dataget)) {
    $curVal = (string)$dataget[1];
    $newVal = ($curVal === 'oncategorys') ? 'offcategorys' : 'oncategorys';
    $setting['statuscategorygenral'] = $newVal;
    update("botsaz", "setting", json_encode($setting), "bot_token", $ApiToken);
    $label = ($newVal === 'oncategorys') ? '✅ فعال' : '❌ غیرفعال';
    sendmessage($from_id, "📂 دسته‌بندی محصولات اکنون: <b>$label</b>\n\nاگر فعال باشد، کاربران هنگام خرید ابتدا دسته‌بندی (یک ماهه، دو ماهه و...) را انتخاب می‌کنند.", $keyboardadmin, 'HTML');
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "دسته‌بندی: $label", 'cache_time' => 1]);
}

// ════════════════════════════════════════════════════════════════════
//  Red Fox — لیست کامل کاربران + مدیریت کامل هر کاربر
// ════════════════════════════════════════════════════════════════════

// ── دکمه لیست کاربران ──
if ($text == "👥 لیست کاربران") {
    $page = 1;
    $perPage = 10;
    $offset = 0;
    $users = [];
    $total = 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM user WHERE bottype = ?");
        $st->execute([$ApiToken]);
        $total = (int)$st->fetchColumn();
        $st2 = $pdo->prepare("SELECT id, username, User_Status, Balance, register FROM user WHERE bottype = ? ORDER BY register DESC LIMIT $perPage OFFSET $offset");
        $st2->execute([$ApiToken]);
        $users = $st2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    if (empty($users)) {
        sendmessage($from_id, "📭 هنوز کاربری ثبت‌نام نکرده است.", $keyboardadmin, 'HTML');
    } else {
        $msg = "👥 <b>لیست کاربران</b> ({$total} نفر)\n\nروی هر کاربر کلیک کنید تا مدیریت کنید:";
        $rows = [];
        foreach ($users as $u) {
            $status = ($u['User_Status'] == 'block') ? '🔴' : '🟢';
            $uname = $u['username'] && $u['username'] != 'none' ? '@' . $u['username'] : '—';
            $rows[] = [['text' => "$status {$u['id']} • $uname", 'callback_data' => "umgmt_" . $u['id'] . "_1"]];
        }
        // صفحه‌بندی
        $navRow = [];
        $totalPages = max(1, ceil($total / $perPage));
        if ($totalPages > 1) {
            $navRow[] = ['text' => "بعدی »", 'callback_data' => "userlist_2"];
        }
        $navRow[] = ['text' => "📄 1/$totalPages", 'callback_data' => "noop"];
        $rows[] = $navRow;
        $rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "admin"]];
        $kb = json_encode(['inline_keyboard' => $rows]);
        sendmessage($from_id, $msg, $kb, 'HTML');
    }
    step('home', $from_id);

// ── صفحه‌بندی لیست کاربران ──
} elseif (preg_match('/^userlist_(\d+)/', $datain, $dataget)) {
    $page = max(1, (int)$dataget[1]);
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $users = [];
    $total = 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM user WHERE bottype = ?");
        $st->execute([$ApiToken]);
        $total = (int)$st->fetchColumn();
        $st2 = $pdo->prepare("SELECT id, username, User_Status, Balance, register FROM user WHERE bottype = ? ORDER BY register DESC LIMIT $perPage OFFSET $offset");
        $st2->execute([$ApiToken]);
        $users = $st2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    $msg = "👥 <b>لیست کاربران</b> ({$total} نفر) — صفحه $page\n\nروی کاربر کلیک کنید:";
    $rows = [];
    foreach ($users as $u) {
        $status = ($u['User_Status'] == 'block') ? '🔴' : '🟢';
        $uname = $u['username'] && $u['username'] != 'none' ? '@' . $u['username'] : '—';
        $rows[] = [['text' => "$status {$u['id']} • $uname", 'callback_data' => "umgmt_" . $u['id'] . "_" . $page]];
    }
    $totalPages = max(1, ceil($total / $perPage));
    $navRow = [];
    if ($page > 1) $navRow[] = ['text' => "« قبلی", 'callback_data' => "userlist_" . ($page - 1)];
    $navRow[] = ['text' => "📄 $page/$totalPages", 'callback_data' => "noop"];
    if ($page < $totalPages) $navRow[] = ['text' => "بعدی »", 'callback_data' => "userlist_" . ($page + 1)];
    $rows[] = $navRow;
    $rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "admin"]];
    $kb = json_encode(['inline_keyboard' => $rows]);
    Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');

// ── پنل مدیریت کامل کاربر ──
} elseif (preg_match('/^umgmt_(\w+)_(\d+)/', $datain, $dataget)) {
    $targetId = (string)$dataget[1];
    $returnPage = (int)$dataget[2];
    $targetUser = select("user", "*", "id", $targetId, "select");
    if (!$targetUser || $targetUser['bottype'] != $ApiToken) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'کاربر یافت نشد', 'show_alert' => true]);
        return;
    }
    // خواندن موجودی از JSON
    $userBal = 0;
    $dataFile = "data/$targetId/$targetId.json";
    if (is_file($dataFile)) { $ud = json_decode(file_get_contents($dataFile), true); $userBal = (int)($ud['Balance'] ?? 0); }
    $isBlocked = ($targetUser['User_Status'] == 'block');
    // شمارش خریدها
    $invCount = 0;
    try { $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? AND bottype = ?"); $st->execute([$targetId, $ApiToken]); $invCount = (int)$st->fetchColumn(); } catch (Throwable $e) {}
    // نام کاربری
    $uname = $targetUser['username'] && $targetUser['username'] != 'none' ? '@' . htmlspecialchars($targetUser['username']) : '—';
    $msg = "👤 <b>مدیریت کاربر</b>\n\n";
    $msg .= "🆔 آیدی: <code>$targetId</code>\n";
    $msg .= "👤 نام: $uname\n";
    $msg .= "💰 موجودی: <b>" . number_format($userBal) . "</b> ت\n";
    $msg .= "🛍 خریدها: <b>$invCount</b>\n";
    $msg .= "📊 وضعیت: " . ($isBlocked ? "🔴 مسدود" : "🟢 فعال");
    $kb = json_encode(['inline_keyboard' => [
        [['text' => '💰 شارژ کیف پول', 'callback_data' => "uaddbal_$targetId"],
         ['text' => '🔻 کسر موجودی', 'callback_data' => "usubbal_$targetId"]],
        [['text' => '⏰ افزایش حجم/زمان', 'callback_data' => "uextend_$targetId"],
         ['text' => '🎁 سرویس جدید', 'callback_data' => "unewsvc_$targetId"]],
        [['text' => $isBlocked ? '✅ فعال‌سازی کاربر' : '⛔ مسدودسازی کاربر', 'callback_data' => "ublock_$targetId"]],
        [['text' => '🔙 بازگشت به لیست', 'callback_data' => "userlist_$returnPage"]],
    ]]);
    Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');

// ── شارژ کیف پول کاربر ──
} elseif (preg_match('/^uaddbal_(\w+)/', $datain, $dataget)) {
    $targetId = (string)$dataget[1];
    savedata('save', 'target_uid', $targetId);
    savedata('save', 'return_after', 'umgmt');
    sendmessage($from_id, "💰 <b>شارژ کیف پول کاربر $targetId</b>\n\nمبلغ افزایش را به تومان ارسال کنید:", $backadmin, 'HTML');
    step('u_add_balance', $from_id);
} elseif ($user['step'] == "u_add_balance" && $text && ctype_digit($text)) {
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetId = $pv['target_uid'] ?? '';
    if ($targetId === '') { sendmessage($from_id, "❌ خطا.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    $amt = (int)$text;
    // آپدیت JSON
    $dataFile = "data/$targetId/$targetId.json";
    $ud = is_file($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['Balance' => 0];
    $newBal = (int)($ud['Balance'] ?? 0) + $amt;
    $ud['Balance'] = $newBal;
    file_put_contents($dataFile, json_encode($ud));
    // آپدیت DB
    update("user", "Balance", $newBal, "id", $targetId);
    sendmessage($from_id, "✅ <code>$amt</code> ت به کیف پول کاربر <code>$targetId</code> اضافه شد.\nموجودی جدید: " . number_format($newBal) . " ت", $keyboardadmin, 'HTML');
    @sendmessage($targetId, "💰 موجودی شما " . number_format($amt) . " ت افزایش یافت.\nموجودی جدید: " . number_format($newBal) . " تومان", null, 'HTML');
    step('home', $from_id);

// ── کسر موجودی ──
} elseif (preg_match('/^usubbal_(\w+)/', $datain, $dataget)) {
    $targetId = (string)$dataget[1];
    savedata('save', 'target_uid', $targetId);
    sendmessage($from_id, "🔻 <b>کسر موجودی کاربر $targetId</b>\n\nمبلغ کسر را ارسال کنید:", $backadmin, 'HTML');
    step('u_sub_balance', $from_id);
} elseif ($user['step'] == "u_sub_balance" && $text && ctype_digit($text)) {
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetId = $pv['target_uid'] ?? '';
    if ($targetId === '') { sendmessage($from_id, "❌ خطا.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    $amt = (int)$text;
    $dataFile = "data/$targetId/$targetId.json";
    $ud = is_file($dataFile) ? json_decode(file_get_contents($dataFile), true) : ['Balance' => 0];
    $newBal = max(0, (int)($ud['Balance'] ?? 0) - $amt);
    $ud['Balance'] = $newBal;
    file_put_contents($dataFile, json_encode($ud));
    update("user", "Balance", $newBal, "id", $targetId);
    sendmessage($from_id, "✅ <code>$amt</code> ت از کیف پول کاربر <code>$targetId</code> کسر شد.\nموجودی جدید: " . number_format($newBal) . " ت", $keyboardadmin, 'HTML');
    step('home', $from_id);

// ── افزایش حجم/زمان ──
} elseif (preg_match('/^uextend_(\w+)/', $datain, $dataget)) {
    $targetId = (string)$dataget[1];
    // پیدا کردن سرویس فعال
    $rxInv = null;
    try {
        $st = $pdo->prepare("SELECT * FROM invoice WHERE id_user = ? AND bottype = ? AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') LIMIT 1");
        $st->execute([$targetId, $ApiToken]);
        $rxInv = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    if (!$rxInv) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این کاربر سرویس فعالی ندارد', 'show_alert' => true]);
        return;
    }
    savedata('save', 'target_uid', $targetId);
    savedata('save', 'extend_invoice', $rxInv['id_invoice']);
    sendmessage($from_id, "⏰ <b>افزایش حجم/زمان</b>\n👤 کاربر: <code>$targetId</code>\n📦 سرویس: <code>{$rxInv['username']}</code>\n\nفرمت: <code>حجم_روز</code> (مثال: <code>5_30</code> = ۵ گیگ + ۳۰ روز):", $backadmin, 'HTML');
    step('u_extend_val', $from_id);
} elseif ($user['step'] == "u_extend_val" && $text) {
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $targetId = $pv['target_uid'] ?? '';
    $invId = $pv['extend_invoice'] ?? '';
    $parts = explode('_', (string)$text);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
        sendmessage($from_id, "❌ فرمت اشتباه. مثال: 5_30", $backadmin, 'HTML'); return;
    }
    $volGB = (int)$parts[0]; $days = (int)$parts[1];
    $rxInv = select("invoice", "*", "id_invoice", $invId, "select");
    if (!$rxInv) { sendmessage($from_id, "❌ سرویس یافت نشد.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    $panel = select("marzban_panel", "*", "name_panel", $rxInv['Service_location'], "select");
    if ($panel && isset($ManagePanel)) {
        $extResult = $ManagePanel->extend("ریست حجم و زمان", $volGB, $days, $rxInv['username'], $rxInv['code_product'] ?? 'custom', $panel['code_panel']);
        if (!empty($extResult['status'])) {
            sendmessage($from_id, "✅ به کاربر <code>$targetId</code>، $volGB گیگ و $days روز اضافه شد.", $keyboardadmin, 'HTML');
            @sendmessage($targetId, "🎁 سرویس شما تمدید شد!\n💾 $volGB گیگ\n⏰ $days روز", null, 'HTML');
        } else {
            sendmessage($from_id, "❌ خطا در تمدید سرویس.", $keyboardadmin, 'HTML');
        }
    } else {
        sendmessage($from_id, "❌ پنل یافت نشد.", $keyboardadmin, 'HTML');
    }
    step('home', $from_id);

// ─ـ فعال/غیرفعال کاربر ──
} elseif (preg_match('/^ublock_(\w+)/', $datain, $dataget)) {
    $targetId = (string)$dataget[1];
    $targetUser = select("user", "*", "id", $targetId, "select");
    if ($targetUser && $targetUser['bottype'] == $ApiToken) {
        $newStatus = ($targetUser['User_Status'] == 'block') ? 'Active' : 'block';
        update("user", "User_Status", $newStatus, "id", $targetId);
        if ($newStatus == 'block') {
            @sendmessage($targetId, "⛔ حساب شما موقتاً مسدود شده است.\n📞 برای پیگیری با پشتیبانی در ارتباط باشید.", null, 'HTML');
        } else {
            @sendmessage($targetId, "✅ حساب شما مجدداً فعال شد. می‌توانید از ربات استفاده کنید.", null, 'HTML');
        }
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $newStatus == 'block' ? '✅ مسدود شد' : '✅ فعال شد', 'cache_time' => 1]);
        // آپدیت پیام با وضعیت جدید
        $isBlocked = ($newStatus == 'block');
        $userBal = 0;
        $dataFile = "data/$targetId/$targetId.json";
        if (is_file($dataFile)) { $ud = json_decode(file_get_contents($dataFile), true); $userBal = (int)($ud['Balance'] ?? 0); }
        $msg = "👤 <b>مدیریت کاربر</b>\n\n🆔 <code>$targetId</code>\n💰 " . number_format($userBal) . " ت\n📊 " . ($isBlocked ? "🔴 مسدود" : "🟢 فعال");
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '💰 شارژ', 'callback_data' => "uaddbal_$targetId"], ['text' => '🔻 کسر', 'callback_data' => "usubbal_$targetId"]],
            [['text' => '⏰ حجم/زمان', 'callback_data' => "uextend_$targetId"]],
            [['text' => $isBlocked ? '✅ فعال‌سازی' : '⛔ مسدودسازی', 'callback_data' => "ublock_$targetId"]],
            [['text' => '🔙 بازگشت', 'callback_data' => "admin"]],
        ]]);
        Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');
    }
}

// ════════════════════════════════════════════════════════════════════
//  Red Fox — مدیریت شماره کارت‌های بانکی ربات نماینده
//  کارت‌ها در reseller_cards ذخیره می‌شوند و هنگام پرداخت رندوم انتخاب می‌شوند
// ════════════════════════════════════════════════════════════════════

rx_require_schema($pdo,['reseller_cards']);

if ($text == "💳 شماره کارت‌ها") {
    // نمایش لیست کارت‌های فعلی
    $cards = [];
    try {
        $st = $pdo->prepare("SELECT * FROM reseller_cards WHERE reseller_id = ? ORDER BY id ASC");
        $st->execute([$dataBase['id_user']]);
        $cards = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    $msg = "💳 <b>مدیریت شماره کارت‌ها</b>\n\n";
    $rows = [];
    if (!empty($cards)) {
        $msg .= "📋 کارت‌های ثبت‌شده:\n";
        foreach ($cards as $c) {
            $cn = htmlspecialchars((string)$c['cardnumber'], ENT_QUOTES, 'UTF-8');
            $nc = htmlspecialchars((string)$c['namecard'], ENT_QUOTES, 'UTF-8');
            $msg .= "• <code>$cn</code> — $nc\n";
            $rows[] = [['text' => "🗑 $cn", 'callback_data' => "delcard_" . (int)$c['id']]];
        }
    } else {
        $msg .= "❌ هیچ کارتی ثبت نشده.\n";
    }
    $msg .= "\n💡 وقتی کاربر پرداخت می‌کند، یکی از کارت‌ها به‌صورت تصادفی نمایش داده می‌شود.";
    $rows[] = [['text' => "➕ افزودن کارت جدید", 'callback_data' => "addcard"]];
    $rows[] = [['text' => "🔙 بازگشت به منوی ادمین", 'callback_data' => "admin"]];
    $kb = json_encode(['inline_keyboard' => $rows]);
    sendmessage($from_id, $msg, $kb, 'HTML');
    step('home', $from_id);

} elseif ($datain == "addcard") {
    sendmessage($from_id, "💳 <b>افزودن کارت جدید</b>\n\nفرمت:\n<code>شماره‌کارت-نام‌صاحب</code>\n\nمثال:\n<code>6037-9911-2345-6789-علی رضایی</code>\n\n💡 شماره کارت را با خط تیره (-) از نام جدا کنید.", $backadmin, 'HTML');
    step('add_new_card', $from_id);
} elseif ($user['step'] == "add_new_card" && $text) {
    $parts = explode('-', (string)$text, 2);
    if (count($parts) === 2) {
        $cardNum = preg_replace('/\s+/', '', trim($parts[0]));
        $cardName = trim($parts[1]);
        if (strlen($cardNum) >= 16 && $cardName !== '') {
            try {
                $pdo->prepare("INSERT INTO reseller_cards (reseller_id, cardnumber, namecard) VALUES (?, ?, ?)")
                    ->execute([$dataBase['id_user'], $cardNum, $cardName]);
                sendmessage($from_id, "✅ کارت اضافه شد:\n<code>$cardNum</code>\n👤 $cardName", $keyboardadmin, 'HTML');
            } catch (Throwable $e) {
                sendmessage($from_id, "❌ خطا در ثبت کارت.", $keyboardadmin, 'HTML');
            }
        } else {
            sendmessage($from_id, "❌ شماره کارت نامعتبر است.", $keyboardadmin, 'HTML');
        }
    } else {
        sendmessage($from_id, "❌ فرمت اشتباه. مثال:\n<code>6037-9911-2345-6789-علی رضایی</code>", $keyboardadmin, 'HTML');
    }
    step('home', $from_id);

} elseif (preg_match('/^delcard_(\d+)/', $datain, $dataget)) {
    $cardId = (int)$dataget[1];
    try {
        $pdo->prepare("DELETE FROM reseller_cards WHERE id = ? AND reseller_id = ?")->execute([$cardId, $dataBase['id_user']]);
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ حذف شد', 'cache_time' => 1]);
    } catch (Throwable $e) {}
    // آپدیت لیست
    $cards = [];
    try { $st = $pdo->prepare("SELECT * FROM reseller_cards WHERE reseller_id = ? ORDER BY id ASC"); $st->execute([$dataBase['id_user']]); $cards = $st->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
    $msg = "💳 <b>شماره کارت‌ها</b>\n\n";
    $rows = [];
    foreach ($cards as $c) {
        $cn = htmlspecialchars((string)$c['cardnumber'], ENT_QUOTES);
        $msg .= "• <code>$cn</code> — " . htmlspecialchars((string)$c['namecard'], ENT_QUOTES, 'UTF-8') . "\n";
        $rows[] = [['text' => "🗑 $cn", 'callback_data' => "delcard_" . (int)$c['id']]];
    }
    if (empty($cards)) $msg .= "هیچ کارتی ثبت نشده.\n";
    $rows[] = [['text' => "➕ افزودن کارت", 'callback_data' => "addcard"]];
    $rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "admin"]];
    $kb = json_encode(['inline_keyboard' => $rows]);
    Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');
}

// ════════════════════════════════════════════════════════════════════
//  Red Fox — پورتال وب اختصاصی نماینده
//  نمایش لینک ورود + یوزرنیم (آیدی عددی) + رمز عبور آماده
//  مانند پنل ادمین اصلی — نماینده مستقیم وارد پنل اختصاصی خود می‌شود
// ════════════════════════════════════════════════════════════════════

rx_require_schema($pdo,[],['user'=>['panel_password']]);

// --- محاسبه مطمئن آدرس پورتال ---
// نکته: domainhosts ستون جدول setting نیست؛ فقط متغیر سراسری config.php است.
$rxPortalBase = rtrim((string)($GLOBALS['domainhosts'] ?? $domainhosts ?? ''), '/');
if ($rxPortalBase === '') {
    // fallback از DOCUMENT_ROOT و مسیر فیزیکی ربات اصلی
    $rxDocRoot = (string)filter_input(INPUT_SERVER, 'DOCUMENT_ROOT');
    $rxBotRoot = (string)realpath(dirname(__DIR__)); // ریشه‌ی ربات اصلی (یک سطح بالاتر از vpnbot/)
    if ($rxDocRoot !== '' && $rxBotRoot !== '' && strpos($rxBotRoot, $rxDocRoot) === 0) {
        $rxPortalBase = trim(substr($rxBotRoot, strlen($rxDocRoot)), '/\\');
    }
}
$rxPortalUrl = 'https://' . $rxPortalBase . '/portal/login.php';
$rxPortalId  = (string)($dataBase['id_user'] ?? $from_id ?? '');

if ($text == "🌐 پورتال وب") {
    // بررسی وضعیت فعلی
    $portalOwner = select("user", "panel_password", "id", $dataBase['id_user'], "select");
    $hasPassword = is_array($portalOwner) && !empty($portalOwner['panel_password']) && (string)$portalOwner['panel_password'] !== '';

    $msg  = "🌐 <b>پورتال وب اختصاصی شما</b>\n\n";
    $msg .= "🔗 آدرس ورود به پورتال:\n<code>$rxPortalUrl</code>\n\n";
    $msg .= "👤 نام کاربری شما (آیدی عددی تلگرام):\n<code>$rxPortalId</code>\n\n";
    $msg .= "📊 وضعیت: " . ($hasPassword ? "🟢 <b>فعال</b>" : "🔴 رمز هنوز تنظیم نشده") . "\n\n";
    $msg .= "🔑 برای دریافت رمز عبور آماده، روی دکمه «🔑 دریافت رمز عبور» بزنید.\n";
    $msg .= "سپس با نام کاربری و رمزی که دریافت می‌کنید، از مرورگر وارد شوید و فقط پنل اختصاصی خودتان را ببینید.\n\n";
    $msg .= "📌 در پورتال می‌توانید:\n";
    $msg .= "• 🛍 مدیریت محصولات و قیمت‌ها\n";
    $msg .= "• 🎨 برند و رنگ‌بندی ربات\n";
    $msg .= "• 💳 کارت‌های بانکی\n";
    $msg .= "• 📊 گزارش فروش";
    $kb = json_encode(['inline_keyboard' => [
        [['text' => '🔑 دریافت رمز عبور', 'callback_data' => "genportalpass"]],
        [['text' => '🔗 باز کردن پورتال', 'url' => $rxPortalUrl]],
        [['text' => '✏️ تنظیم رمز دلخواه', 'callback_data' => "setportalpass"]],
        [['text' => '🔙 بازگشت به منوی ادمین', 'callback_data' => "admin"]],
    ]]);
    sendmessage($from_id, $msg, $kb, 'HTML');
    step('home', $from_id);

} elseif ($datain == "genportalpass") {
    // ساخت رمز تصادفی قوی، ذخیره‌ی هش، و نمایش متن خام (آماده برای ورود)
    $rxNewPass = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    $rxHashed  = password_hash($rxNewPass, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("UPDATE user SET panel_password = ? WHERE id = ?")->execute([$rxHashed, $dataBase['id_user']]);
        $msg  = "✅ <b>اطلاعات ورود به پورتال شما</b>\n\n";
        $msg .= "🔗 آدرس ورود:\n<code>$rxPortalUrl</code>\n\n";
        $msg .= "👤 نام کاربری:\n<code>$rxPortalId</code>\n";
        $msg .= "🔑 رمز عبور:\n<code>$rxNewPass</code>\n\n";
        $msg .= "💡 همین حالا روی «🔗 باز کردن پورتال» بزنید و با اطلاعات بالا وارد شوید!\n\n";
        $msg .= "⚠️ این پیام را ذخیره کنید.\n";
        $msg .= "🔄 برای ساخت رمز جدید هر زمان می‌توانید دوباره «🔑 دریافت رمز عبور» را بزنید.";
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '🔗 باز کردن پورتال', 'url' => $rxPortalUrl]],
            [['text' => '🔄 رمز جدید', 'callback_data' => "genportalpass"]],
            [['text' => '🔙 بازگشت به منوی ادمین', 'callback_data' => "admin"]],
        ]]);
        Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ رمز ورود ساخته شد', 'cache_time' => 2]);
    } catch (Throwable $e) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ خطا در ساخت رمز. دوباره تلاش کنید.', 'show_alert' => true, 'cache_time' => 3]);
    }

} elseif ($datain == "setportalpass" || $datain == "changeportalpass") {
    $actionLabel = ($datain == "changeportalpass") ? "🔄 تغییر" : "✏️ تنظیم";
    sendmessage($from_id, $actionLabel . " <b>رمز عبور دلخواه پورتال</b>\n\nرمز عبور موردنظر خود را ارسال کنید (حداقل ۱۰ کاراکتر):\n\n💡 یا برای دریافت رمز تصادفی به منوی پورتال برگردید و «🔑 دریافت رمز عبور» را بزنید.", $backadmin, 'HTML');
    step('set_portal_password', $from_id);

} elseif ($user['step'] == "set_portal_password" && $text) {
    $pass = trim((string)$text);
    if (mb_strlen($pass) < 10) {
        sendmessage($from_id, "❌ رمز عبور باید حداقل ۱۰ کاراکتر باشد. دوباره ارسال کنید:", $backadmin, 'HTML');
        return;
    }
    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("UPDATE user SET panel_password = ? WHERE id = ?")->execute([$hashed, $dataBase['id_user']]);
        $msg  = "✅ <b>رمز عبور پورتال شما تنظیم شد!</b>\n\n";
        $msg .= "🔗 آدرس ورود:\n<code>$rxPortalUrl</code>\n\n";
        $msg .= "👤 نام کاربری:\n<code>$rxPortalId</code>\n";
        $msg .= "🔑 رمز عبور شما: <code>$pass</code>\n\n";
        $msg .= "💡 همین حالا از مرورگر وارد شوید!\n";
        $msg .= "⚠️ این پیام را ذخیره کنید — رمز عبور به‌صورت هش ذخیره شده و قابل بازیابی نیست.";
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '🔗 باز کردن پورتال', 'url' => $rxPortalUrl]],
            [['text' => '🔙 بازگشت به منوی ادمین', 'callback_data' => "admin"]],
        ]]);
        sendmessage($from_id, $msg, $kb, 'HTML');
    } catch (Throwable $e) {
        error_log('[vpnbot admin] password save failed: ' . get_class($e));
        sendmessage($from_id, '❌ ذخیره رمز عبور به‌دلیل خطای داخلی انجام نشد.', $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
}

// ════════════════════════════════════════════════════════════════════
//  Red Fox — مدیریت محصولات (ساخت + لیست)
// ════════════════════════════════════════════════════════════════════

// ── محصول جدید ──
if ($text == "➕ محصول جدید" || $datain == "addnewproduct") {
    $cats = [];
    try { $cats = $pdo->query("SELECT * FROM category ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
    $msg = "🛍 <b>ساخت محصول جدید</b>\n\n";
    $msg .= "فرمت:\n<code>نام-حجم-زمان-قیمت-دسته</code>\n\n";
    $msg .= "مثال:\n<code>ماهانه ۱۰ گیگ-10-30-50000-یک ماهه</code>\n";
    $msg .= "(نام - حجم GB - زمان روز - قیمت تومان - دسته‌بندی)\n\n";
    $msg .= "💡 حجم 0 = نامحدود | زمان 0 = نامحدود\n";
    $msg .= "💡 دسته‌بندی اختیاری است (خط تیره آخر خالی)\n";
    if (!empty($cats)) {
        $msg .= "\n📂 دسته‌بندی‌های موجود:\n";
        foreach ($cats as $cat) { $msg .= "• " . htmlspecialchars((string)$cat['remark'], ENT_QUOTES, 'UTF-8') . "\n"; }
    }
    sendmessage($from_id, $msg, $backadmin, 'HTML');
    step('create_new_product', $from_id);
} elseif ($user['step'] == "create_new_product" && $text) {
    $parts = explode('-', (string)$text);
    if (count($parts) >= 4) {
        $pName = trim($parts[0]); $pVol = (int)trim($parts[1]); $pTime = (int)trim($parts[2]); $pPrice = (int)trim($parts[3]);
        $pCategory = isset($parts[4]) ? trim($parts[4]) : '';
        if ($pName !== '' && $pPrice > 0) {
            $pCode = 'vp_' . bin2hex(random_bytes(3));
            try {
                $stmt = $pdo->prepare("INSERT INTO product (code_product, name_product, price_product, Volume_constraint, Service_time, agent, Location, category, note, data_limit_reset, hide_panel) VALUES (:code, :name, :price, :vol, :time, :agent, '/all', :cat, '', 'no_reset', '{}')");
                $stmt->bindValue(':code', $pCode); $stmt->bindValue(':name', $pName);
                $stmt->bindValue(':price', (string)$pPrice); $stmt->bindValue(':vol', (string)$pVol);
                $stmt->bindValue(':time', (string)$pTime); $stmt->bindValue(':agent', $userbot['agent'] ?? 'all');
                $stmt->bindValue(':cat', $pCategory);
                $stmt->execute();
                $msg = "✅ محصول «{$pName}» ساخته شد!\n📦 حجم: " . ($pVol ?: 'نامحدود') . " GB\n⏳ زمان: " . ($pTime ?: 'نامحدود') . " روز\n💶 قیمت: " . number_format($pPrice) . " ت";
                if ($pCategory !== '') $msg .= "\n📂 دسته: {$pCategory}";
                $msg .= "\n🔑 کد: <code>$pCode</code>";
                sendmessage($from_id, $msg, $keyboardadmin, 'HTML');
            } catch (Throwable $e) { error_log('[vpnbot admin] product create failed: ' . get_class($e)); sendmessage($from_id, '❌ ساخت محصول به‌دلیل خطای داخلی انجام نشد.', $keyboardadmin, 'HTML'); }
        } else { sendmessage($from_id, "❌ نام و قیمت الزامی است.", $keyboardadmin, 'HTML'); }
    } else { sendmessage($from_id, "❌ فرمت اشتباه.\nمثال: <code>ماهانه ۱۰ گیگ-10-30-50000-یک ماهه</code>", $keyboardadmin, 'HTML'); }
    step('home', $from_id);

// ── لیست محصولات ──
} elseif ($text == "📋 لیست محصولات" || $datain == "productlist") {
    $products = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE agent = ? OR agent = 'all' ORDER BY CAST(price_product AS UNSIGNED) ASC LIMIT 50");
        $stmt->execute([$userbot['agent'] ?? 'all']);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    if (empty($products)) {
        $msg = "📭 محصولی یافت نشد.\n\nبرای ساخت: «➕ محصول جدید»";
        $kb = json_encode(['inline_keyboard' => [[['text' => "➕ محصول جدید", 'callback_data' => "addnewproduct"]], [['text' => "🔙 بازگشت", 'callback_data' => "admin"]]]]);
    } else {
        $msg = "📋 <b>لیست محصولات</b>\n\n";
        $rows = [];
        foreach ($products as $p) {
            $pName = htmlspecialchars((string)$p['name_product'], ENT_QUOTES, 'UTF-8');
            $pPrice = number_format((int)$p['price_product']);
            $pVol = $p['Volume_constraint'] ?: '∞';
            $pTime = $p['Service_time'] ?: '∞';
            $pCat = $p['category'] ?: '';
            $msg .= "• <b>{$pName}</b> — {$pPrice} ت ({$pVol}GB/{$pTime}روز)";
            if ($pCat) $msg .= " [{$pCat}]";
            $msg .= "\n";
        }
        $rows[] = [['text' => "➕ محصول جدید", 'callback_data' => "addnewproduct"]];
        $rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "admin"]];
        $kb = json_encode(['inline_keyboard' => $rows]);
    }
    if ($datain == "productlist") { Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML'); }
    else { sendmessage($from_id, $msg, $kb, 'HTML'); }
    step('home', $from_id);
}

// ── گزارش ربات ──
if ($text == "📬 گزارش ربات") {
    $statistics = select("user", "*", "bottype", $ApiToken, "count");
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT id_user) as count FROM invoice WHERE name_product != 'سرویس تست' AND bottype = ?");
    $stmt2->execute([$ApiToken]);
    $buyers = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];
    $sql1 = "SELECT COUNT(*) AS c FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست' AND bottype = '$ApiToken'";
    $sales = (int)$pdo->query($sql1)->fetch(PDO::FETCH_ASSOC)['c'];
    $sql2 = "SELECT COALESCE(SUM(price_product),0) AS t FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست' AND bottype = '$ApiToken'";
    $revenue = (int)$pdo->query($sql2)->fetch(PDO::FETCH_ASSOC)['t'];
    $sql3 = "SELECT COUNT(*) as c FROM Payment_report WHERE payment_Status = 'paid' AND bottype = '$ApiToken'";
    $paidCount = (int)$pdo->query($sql3)->fetch(PDO::FETCH_ASSOC)['c'];
    $sql4 = "SELECT COALESCE(SUM(price),0) as t FROM Payment_report WHERE payment_Status = 'paid' AND bottype = '$ApiToken'";
    $paidTotal = (int)$pdo->query($sql4)->fetch(PDO::FETCH_ASSOC)['t'];
    $sql5 = "SELECT COUNT(*) as c FROM Payment_report WHERE payment_Status = 'Unpaid' AND bottype = '$ApiToken'";
    $pendingCount = (int)$pdo->query($sql5)->fetch(PDO::FETCH_ASSOC)['c'];
    $msg = "📬 <b>گزارش جامع ربات</b>\n\n";
    $msg .= "👥 تعداد کاربران: <b>$statistics</b>\n";
    $msg .= "🛒 کاربرانی که خرید داشته‌اند: <b>$buyers</b>\n";
    $msg .= "📦 تعداد فروش: <b>$sales</b>\n";
    $msg .= "💰 درآمد فروش: <b>" . number_format($revenue) . "</b> ت\n";
    $msg .= "✅ پرداخت‌های تأییدشده: <b>$paidCount</b> (مجموع: " . number_format($paidTotal) . " ت)\n";
    $msg .= "⏳ در انتظار تأیید: <b>$pendingCount</b>\n";
    sendmessage($from_id, $msg, $keyboardadmin, 'HTML');
    step('home', $from_id);
}

// ════════════════════════════════════════════════════════════════════
//  Red Fox — منوی سوپر نماینده
// ════════════════════════════════════════════════════════════════════
$rxOwnerId = (string)($dataBase['id_user'] ?? '');
$rxIsSuper = function_exists('rxVpnbotIsSuper') ? rxVpnbotIsSuper($rxOwnerId) : false;

if (($text == "⭐ سوپر نماینده" || $datain == "super_menu") && $rxIsSuper) {
    $rxPerms = function_exists('rxVpnbotPerms') ? rxVpnbotPerms($rxOwnerId) : [];
    $rxRows = [];
    if (!empty($rxPerms['products']))     $rxRows[] = [['text' => "🛍 مدیریت محصولات", 'callback_data' => "super_products"]];
    if (!empty($rxPerms['categories']))   $rxRows[] = [['text' => "📂 دسته‌بندی‌ها", 'callback_data' => "super_categories"]];
    if (!empty($rxPerms['extend_user']))  $rxRows[] = [['text' => "⏰ افزایش زمان/حجم کاربر", 'callback_data' => "super_extend"]];
    if (!empty($rxPerms['charge_user']))  $rxRows[] = [['text' => "💰 شارژ کیف پول کاربر", 'callback_data' => "super_charge"]];
    if (!empty($rxPerms['manage_users'])) $rxRows[] = [['text' => "👥 جستجوی کاربر", 'callback_data' => "super_search"]];
    if (!empty($rxPerms['reports']))      $rxRows[] = [['text' => "📊 گزارش فروش", 'callback_data' => "super_reports"]];
    $rxRows[] = [['text' => "🔙 بازگشت به منوی ادمین", 'callback_data' => "admin"]];
    $rxKb = json_encode(['inline_keyboard' => $rxRows]);
    $rxMsg = "⭐ <b>پنل سوپر نماینده</b>\n\nقابلیت‌های ویژه‌ی شما:";
    if ($datain == "super_menu") {
        Editmessagetext($from_id, $message_id, $rxMsg, $rxKb, 'HTML');
    } else {
        sendmessage($from_id, $rxMsg, $rxKb, 'HTML');
    }
    step('home', $from_id);

// ── مدیریت محصولات (سوپر) ──
} elseif ($datain == "super_products" && rxVpnbotHasPerm($rxOwnerId, 'products')) {
    $rxProdList = readJsonFileIfExists(__DIR__ . '/product.json', []);
    $rxMsg = "🛍 <b>مدیریت محصولات</b>\n\n📋 قیمت‌های دلخواه:\n";
    if (empty($rxProdList)) { $rxMsg .= "هیچ قیمت دلخواهی تنظیم نشده.\n"; }
    foreach ($rxProdList as $code => $price) { $rxMsg .= "• <code>$code</code> → " . number_format((int)$price) . " ت\n"; }
    $rxMsg .= "\n💡 برای ساخت محصول جدید از منوی ادمین «➕ محصول جدید» را بزنید.";
    $rxKb = json_encode(['inline_keyboard' => [
        [['text' => "➕ افزودن/ویرایش قیمت", 'callback_data' => "super_addprice"]],
        [['text' => "🗑 حذف قیمت محصول", 'callback_data' => "super_delprice"]],
        [['text' => "🔙 بازگشت", 'callback_data' => "super_menu"]],
    ]]);
    Editmessagetext($from_id, $message_id, $rxMsg, $rxKb, 'HTML');

} elseif ($datain == "super_addprice" && rxVpnbotHasPerm($rxOwnerId, 'products')) {
    sendmessage($from_id, "📌 کد محصول و قیمت را ارسال کنید:\nمثال: <code>prod_001-50000</code>", $backadmin, 'HTML');
    step('super_setprice', $from_id);
} elseif ($user['step'] == "super_setprice" && $text) {
    $parts = explode('-', (string)$text);
    if (count($parts) === 2 && ctype_digit($parts[1])) {
        $code = trim($parts[0]); $price = (int)$parts[1];
        $rxProdList = readJsonFileIfExists(__DIR__ . '/product.json', []);
        $rxProdList[$code] = $price;
        file_put_contents(__DIR__ . '/product.json', json_encode($rxProdList));
        sendmessage($from_id, "✅ قیمت <code>$code</code> = " . number_format($price) . " ت", $keyboardadmin, 'HTML');
    } else { sendmessage($from_id, "❌ فرمت اشتباه.", $keyboardadmin, 'HTML'); }
    step('home', $from_id);

} elseif ($datain == "super_delprice" && rxVpnbotHasPerm($rxOwnerId, 'products')) {
    sendmessage($from_id, "📌 کد محصول را ارسال کنید:", $backadmin, 'HTML');
    step('super_delprice', $from_id);
} elseif ($user['step'] == "super_delprice" && $text) {
    $code = trim((string)$text);
    $rxProdList = readJsonFileIfExists(__DIR__ . '/product.json', []);
    if (isset($rxProdList[$code])) { unset($rxProdList[$code]); file_put_contents(__DIR__ . '/product.json', json_encode($rxProdList)); sendmessage($from_id, "✅ حذف شد.", $keyboardadmin, 'HTML'); }
    else { sendmessage($from_id, "❌ یافت نشد.", $keyboardadmin, 'HTML'); }
    step('home', $from_id);

// ── دسته‌بندی‌ها (سوپر) ──
} elseif ($datain == "super_categories" && rxVpnbotHasPerm($rxOwnerId, 'categories')) {
    $cats = [];
    try { $cats = $pdo->query("SELECT * FROM category ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
    $msg = "📂 <b>مدیریت دسته‌بندی‌ها</b>\n\n";
    $rows = [];
    if (!empty($cats)) {
        foreach ($cats as $cat) {
            $msg .= "• " . htmlspecialchars((string)$cat['remark'], ENT_QUOTES, 'UTF-8') . "\n";
            $rows[] = [['text' => "🗑 " . htmlspecialchars((string)$cat['remark'], ENT_QUOTES, 'UTF-8'), 'callback_data' => "delcat_" . (int)$cat['id']]];
        }
    } else { $msg .= "هیچ دسته‌بندی وجود ندارد.\n"; }
    $rows[] = [['text' => "➕ دسته‌بندی جدید", 'callback_data' => "addcat"]];
    $rows[] = [['text' => "🔙 بازگشت", 'callback_data' => "super_menu"]];
    $kb = json_encode(['inline_keyboard' => $rows]);
    Editmessagetext($from_id, $message_id, $msg, $kb, 'HTML');

} elseif ($datain == "addcat") {
    sendmessage($from_id, "📂 نام دسته‌بندی جدید را ارسال کنید:", $backadmin, 'HTML');
    step('super_create_cat', $from_id);
} elseif ($user['step'] == "super_create_cat" && $text) {
    $catName = trim((string)$text);
    if ($catName !== '') {
        try {
            $pdo->prepare("INSERT INTO category (remark) VALUES (?)")->execute([$catName]);
            sendmessage($from_id, "✅ دسته‌بندی «{$catName}» ساخته شد!", $keyboardadmin, 'HTML');
        } catch (Throwable $e) { sendmessage($from_id, "❌ خطا.", $keyboardadmin, 'HTML'); }
    }
    step('home', $from_id);

} elseif (preg_match('/delcat_(\d+)/', $datain, $dataget)) {
    $catId = (int)$dataget[1];
    try { $pdo->prepare("DELETE FROM category WHERE id = ?")->execute([$catId]); } catch (Throwable $e) {}
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ حذف شد', 'cache_time' => 1]);

// ── شارژ کاربر (سوپر) ──
} elseif ($datain == "super_charge" && rxVpnbotHasPerm($rxOwnerId, 'charge_user')) {
    sendmessage($from_id, "💰 <b>شارژ کیف پول کاربر</b>\n\nآیدی عددی کاربر را ارسال کنید:", $backadmin, 'HTML');
    step('super_charge_uid', $from_id);
} elseif ($user['step'] == "super_charge_uid" && $text) {
    if (!ctype_digit($text)) { sendmessage($from_id, "❌ آیدی عددی معتبر.", $backadmin, 'HTML'); return; }
    $rxTarget = select("user", "*", "id", (string)$text, "select");
    if (!$rxTarget || $rxTarget['bottype'] != $ApiToken) { sendmessage($from_id, "❌ کاربر متعلق به ربات شما نیست.", $backadmin, 'HTML'); step('home', $from_id); return; }
    savedata('save', 'charge_uid', $text);
    sendmessage($from_id, "👤 کاربر: <code>$text</code>\nموجودی: " . number_format((int)$rxTarget['Balance']) . " ت\n\nمبلغ شارژ را ارسال کنید:", $backadmin, 'HTML');
    step('super_charge_amt', $from_id);
} elseif ($user['step'] == "super_charge_amt" && $text) {
    $amt = (int)$text;
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $uid = $pv['charge_uid'] ?? '';
    if ($uid === '' || $amt === 0) { sendmessage($from_id, "❌ نامعتبر.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    $rxTarget = select("user", "*", "id", $uid, "select");
    $newBal = (int)$rxTarget['Balance'] + $amt; if ($newBal < 0) $newBal = 0;
    update("user", "Balance", $newBal, "id", $uid);
    $dataFile = __DIR__ . "/data/$uid/$uid.json";
    if (is_file($dataFile)) { $ud = json_decode(file_get_contents($dataFile), true) ?: []; $ud['Balance'] = $newBal; file_put_contents($dataFile, json_encode($ud)); }
    sendmessage($from_id, "✅ موجودی کاربر <code>$uid</code> تغییر کرد.\nمبلغ: " . ($amt > 0 ? '+' : '') . number_format($amt) . " ت\nموجودی جدید: " . number_format($newBal) . " ت", $keyboardadmin, 'HTML');
    @sendmessage($uid, "💰 موجودی شما تغییر یافت.\nموجودی جدید: " . number_format($newBal) . " تومان", null, 'HTML');
    step('home', $from_id);

// ── افزایش حجم/زمان (سوپر) ──
} elseif ($datain == "super_extend" && rxVpnbotHasPerm($rxOwnerId, 'extend_user')) {
    sendmessage($from_id, "⏰ <b>افزایش حجم/زمان</b>\n\nآیدی عددی کاربر را ارسال کنید:", $backadmin, 'HTML');
    step('super_extend_uid', $from_id);
} elseif ($user['step'] == "super_extend_uid" && $text) {
    if (!ctype_digit($text)) { sendmessage($from_id, "❌ آیدی عددی معتبر.", $backadmin, 'HTML'); return; }
    $rxInv = null;
    try { $st = $pdo->prepare("SELECT * FROM invoice WHERE id_user = ? AND bottype = ? AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') LIMIT 1"); $st->execute([$text, $ApiToken]); $rxInv = $st->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
    if (!$rxInv) { sendmessage($from_id, "❌ سرویس فعالی یافت نشد.", $backadmin, 'HTML'); step('home', $from_id); return; }
    savedata('save', 'extend_uid', $text);
    savedata('save', 'extend_invoice', $rxInv['id_invoice']);
    sendmessage($from_id, "👤 کاربر: <code>$text</code>\n📦 سرویس: <code>{$rxInv['username']}</code>\n\nفرمت: <code>حجم_روز</code> (مثال: <code>5_30</code>):", $backadmin, 'HTML');
    step('super_extend_val', $from_id);
} elseif ($user['step'] == "super_extend_val" && $text) {
    $pv = $user['Processing_value'] ? json_decode($user['Processing_value'], true) : [];
    $uid = $pv['extend_uid'] ?? ''; $invId = $pv['extend_invoice'] ?? '';
    $parts = explode('_', (string)$text);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) { sendmessage($from_id, "❌ فرمت اشتباه. مثال: 5_30", $backadmin, 'HTML'); return; }
    $volGB = (int)$parts[0]; $days = (int)$parts[1];
    $rxInv = select("invoice", "*", "id_invoice", $invId, "select");
    if (!$rxInv) { sendmessage($from_id, "❌ سرویس یافت نشد.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    $panel = select("marzban_panel", "*", "name_panel", $rxInv['Service_location'], "select");
    if ($panel && isset($ManagePanel)) {
        $extResult = $ManagePanel->extend("ریست حجم و زمان", $volGB, $days, $rxInv['username'], $rxInv['code_product'] ?? 'custom', $panel['code_panel']);
        if (!empty($extResult['status'])) {
            sendmessage($from_id, "✅ به کاربر <code>$uid</code>، $volGB گیگ و $days روز اضافه شد.", $keyboardadmin, 'HTML');
            @sendmessage($uid, "🎁 سرویس شما تمدید شد!\n💾 $volGB گیگ\n⏰ $days روز", null, 'HTML');
        } else { sendmessage($from_id, "❌ خطا در تمدید.", $keyboardadmin, 'HTML'); }
    } else { sendmessage($from_id, "❌ پنل یافت نشد.", $keyboardadmin, 'HTML'); }
    step('home', $from_id);

// ── جستجوی کاربر (سوپر) ──
} elseif ($datain == "super_search" && rxVpnbotHasPerm($rxOwnerId, 'manage_users')) {
    sendmessage($from_id, "🔍 آیدی عددی یا یوزرنیم کاربر را ارسال کنید:", $backadmin, 'HTML');
    step('super_search_q', $from_id);
} elseif ($user['step'] == "super_search_q" && $text) {
    $q = trim((string)$text);
    $rxTarget = ctype_digit($q) ? select("user", "*", "id", $q, "select") : null;
    if (!$rxTarget) { try { $st = $pdo->prepare("SELECT * FROM user WHERE username = ? AND bottype = ? LIMIT 1"); $st->execute([$q, $ApiToken]); $rxTarget = $st->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {} }
    if (!$rxTarget || $rxTarget['bottype'] != $ApiToken) { sendmessage($from_id, "❌ کاربر یافت نشد.", $keyboardadmin, 'HTML'); step('home', $from_id); return; }
    $rxMsg = "👤 <b>اطلاعات کاربر</b>\n\n🆔 آیدی: <code>{$rxTarget['id']}</code>\n👤 نام: @" . htmlspecialchars((string)$rxTarget['username']) . "\n💰 موجودی: " . number_format((int)$rxTarget['Balance']) . " ت\n📊 وضعیت: " . htmlspecialchars((string)$rxTarget['User_Status']);
    sendmessage($from_id, $rxMsg, $keyboardadmin, 'HTML');
    step('home', $from_id);

// ── گزارش فروش (سوپر) ──
} elseif ($datain == "super_reports" && rxVpnbotHasPerm($rxOwnerId, 'reports')) {
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE bottype = '" . mysqli_real_escape_string($connect, $ApiToken) . "' AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')")->fetchColumn();
        $rev = (int)$pdo->query("SELECT COALESCE(SUM(CAST(price_product AS UNSIGNED)),0) FROM invoice WHERE bottype = '" . mysqli_real_escape_string($connect, $ApiToken) . "' AND Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')")->fetchColumn();
        $users = (int)$pdo->query("SELECT COUNT(DISTINCT id_user) FROM invoice WHERE bottype = '" . mysqli_real_escape_string($connect, $ApiToken) . "'")->fetchColumn();
    } catch (Throwable $e) { $cnt = 0; $rev = 0; $users = 0; }
    $rxMsg = "📊 <b>گزارش فروش</b>\n\n🛍 فروش موفق: <b>$cnt</b>\n💰 درآمد: <b>" . number_format($rev) . " ت</b>\n👥 مشتریان: <b>$users</b>";
    sendmessage($from_id, $rxMsg, $keyboardadmin, 'HTML');
    step('home', $from_id);
}
