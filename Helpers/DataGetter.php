<?php

namespace EvanG\Modules\MailSystem\Helpers;

use EvanG\Modules\MailSystem\Settings;
use Fisharebest\Webtrees\Tree;

interface DataGetter
{
    function get(Settings $args, Tree $tree);
}