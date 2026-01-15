<?php

function checkRole($allowedRoles) {
    
    if (!isset($_SESSION['user'])) {
        echo "<script>window.location.href='login.php?error_msg=Login first'</script>";
        exit();
      
    }

    $userRole = $_SESSION['user']['USER_TYPE'];

    if ($userRole < $allowedRoles) {
        if($userRole == 1)
        {
            echo "<script>window.location.href='new_user.php'</script>";
            exit();
        }
        else
        {
            echo "<script>window.location.href='unauthorized.php'</script>";
            exit();
        }
    }
}



?>