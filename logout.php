<?php

require __DIR__ . '/bootstrap/app.php';

Auth::logout();
redirect_to('index.php');
