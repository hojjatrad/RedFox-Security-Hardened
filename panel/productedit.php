<?php
require_once __DIR__ . '/../lib/Security.php';
redfox_secure_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../function.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

$statusmessage = false;
$infomesssage  = "";
$id_product = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = $id_product === false ? false : select("product", "*", "id", $id_product, "select");

$panelQuery = $pdo->prepare("SELECT name_panel FROM marzban_panel ORDER BY id ASC");
$panelQuery->execute();
$listpanel = $panelQuery->fetchAll(PDO::FETCH_ASSOC);

if ($product == false) {
    $statusmessage = true;
    $infomesssage  = "محصول مورد نظر یافت نشد!";
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $nameProduct = trim((string)($_POST['name_product'] ?? ''));
    $priceProduct = filter_var($_POST['price_product'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $volumeConstraint = filter_var($_POST['Volume_constraint'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $serviceTime = filter_var($_POST['Service_time'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $agent = (string)($_POST['agent'] ?? '');
    $category = trim((string)($_POST['category'] ?? ''));
    $locationPost = trim((string)($_POST['Location'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $allowedLocations = array_column($listpanel, 'name_panel');
    $allowedLocations[] = '/all';

    if ($nameProduct === '' || mb_strlen($nameProduct) > 190) {
        $statusmessage = true; $infomesssage = "نام محصول نامعتبر است.";
    } elseif ($priceProduct === false) {
        $statusmessage = true; $infomesssage = "مبلغ محصول باید عدد صحیح نامنفی باشد";
    } elseif ($volumeConstraint === false) {
        $statusmessage = true; $infomesssage = "حجم محصول باید عدد صحیح نامنفی باشد";
    } elseif ($serviceTime === false) {
        $statusmessage = true; $infomesssage = "زمان محصول باید عدد صحیح مثبت باشد";
    } elseif (!in_array($agent, ['f', 'n', 'n2'], true)) {
        $statusmessage = true; $infomesssage = "گروه کاربری نامعتبر است";
    } elseif (!in_array($locationPost, $allowedLocations, true)) {
        $statusmessage = true; $infomesssage = "پنل انتخاب‌شده نامعتبر است";
    } elseif (mb_strlen($category) > 190 || mb_strlen($note) > 2000) {
        $statusmessage = true; $infomesssage = "طول دسته‌بندی یا یادداشت بیش از حد مجاز است";
    } else {
        $duplicate = $pdo->prepare('SELECT 1 FROM product WHERE name_product=:name AND id<>:id LIMIT 1');
        $duplicate->execute([':name' => $nameProduct, ':id' => $id_product]);
        if ($duplicate->fetchColumn()) {
            $statusmessage = true; $infomesssage = "نام محصول تکراری است.";
        } else {
            $save = $pdo->prepare('UPDATE product SET name_product=:name, price_product=:price, Volume_constraint=:volume, Service_time=:service_time, agent=:agent, category=:category, Location=:location, note=:note WHERE id=:id');
            $save->execute([
                ':name'=>$nameProduct, ':price'=>$priceProduct, ':volume'=>$volumeConstraint,
                ':service_time'=>$serviceTime, ':agent'=>$agent, ':category'=>$category,
                ':location'=>$locationPost, ':note'=>$note, ':id'=>$id_product,
            ]);
            header('Location: product.php', true, 303);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ویرایش محصول | ربات رد فاکس</title>
    <link rel="stylesheet" href="css/theme.css">
<script src="js/theme.js" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('pen-to-square', 'svg-icon svg-lg'); ?>
                        ویرایش محصول
                    </div>
                    <div class="page-head__sub">
                        <a href="product.php" class="text-link">
                            <?php echo icon('arrow-right', 'svg-icon'); ?> بازگشت به لیست محصولات
                        </a>
                    </div>
                </div>
            </div>

            <div class="card" style="max-width:820px; margin: 0 auto;">

                <?php if ($statusmessage): ?>
                    <div class="alert alert-error">
                        <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                        <span><?php echo $infomesssage; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($product): ?>
                <form action="productedit.php?id=<?php echo (int)$id_product; ?>" method="POST">
                    <input type="hidden" name="action" value="save">

                    <div class="form-group">
                        <label class="form-label">نام محصول</label>
                        <input type="text" name="name_product" class="form-control" value="<?php echo htmlspecialchars($product['name_product'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">قیمت (تومان)</label>
                            <input type="number" name="price_product" class="form-control" value="<?php echo htmlspecialchars($product['price_product'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">حجم (GB)</label>
                            <input type="number" name="Volume_constraint" class="form-control" value="<?php echo htmlspecialchars($product['Volume_constraint'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">زمان (روز)</label>
                            <input type="number" name="Service_time" class="form-control" value="<?php echo htmlspecialchars($product['Service_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">نوع کاربر</label>
                            <select name="agent" class="form-control">
                                <option value="f"  <?php if ($product['agent']=='f')  echo 'selected'; ?>>کاربر عادی</option>
                                <option value="n"  <?php if ($product['agent']=='n')  echo 'selected'; ?>>نماینده معمولی</option>
                                <option value="n2" <?php if ($product['agent']=='n2') echo 'selected'; ?>>نماینده پیشرفته</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">پنل (لوکیشن)</label>
                        <select name="Location" class="form-control" required>
                            <option value="/all" <?php if ($product['Location'] == '/all') echo 'selected'; ?>>تمامی پنل‌ها</option>
                            <?php
                            $currentLoc = (string)($product['Location'] ?? '');
                            $foundCurrent = ($currentLoc === '/all');
                            foreach ($listpanel as $panel):
                                $pname = (string)($panel['name_panel'] ?? '');
                                if ($pname === $currentLoc) $foundCurrent = true;
                            ?>
                                <option value="<?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($pname === $currentLoc) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!$foundCurrent && $currentLoc !== ''): ?>
                                <option value="<?php echo htmlspecialchars($currentLoc, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                    <?php echo htmlspecialchars($currentLoc, ENT_QUOTES, 'UTF-8'); ?> (حذف‌شده)
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">دسته‌بندی</label>
                        <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">یادداشت</label>
                        <textarea name="note" class="form-control" rows="3"><?php echo htmlspecialchars($product['note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo icon('check', 'svg-icon'); ?> ذخیره تغییرات
                    </button>

                </form>
                <?php endif; ?>
            </div>

        </div>
    </section>
</section>

</body>
</html>


