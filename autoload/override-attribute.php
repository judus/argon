<?php

declare(strict_types=1);

if (\PHP_VERSION_ID < 80300 && !class_exists('Override', false)) {
    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class Override
    {
    }
}
