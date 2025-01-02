<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem\Helpers;

use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use Throwable;

class CompatibilityHelper
{
    public static function getService(string $service): mixed
    {
        try {
            if (version_compare(Webtrees::VERSION, '2.2.0', '>='))
                return Registry::container()->get($service);
            else return app($service);
        } catch (Throwable $e) {
            Log::addErrorLog($e->getMessage());
            return null;
        }
    }
}