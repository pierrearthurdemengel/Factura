<?php

$preloadFile = dirname(__DIR__) . '/var/cache/prod/App_KernelProdContainer.preload.php';

if (file_exists($preloadFile)) {
    require_once $preloadFile;
}
