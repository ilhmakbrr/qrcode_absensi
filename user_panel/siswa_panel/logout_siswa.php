<?php
session_name('SISWA_SESSION');
session_start();
session_destroy();
header("Location: ../auth_user/login_user.php?role=siswa");
exit();