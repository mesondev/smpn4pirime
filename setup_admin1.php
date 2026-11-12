<?php
// Keep the legacy URL, but send every setup request through the secured canonical flow.
header('Location: setup_admin.php', true, 302);
exit;
