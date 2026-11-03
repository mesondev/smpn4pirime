<?php

declare(strict_types=1);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
header('Location: detail_berita.php?id=' . $id, true, 301);
exit;
