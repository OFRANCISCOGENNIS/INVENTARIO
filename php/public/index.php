<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Inventario\Presentation\Aplicacao;
use Inventario\Presentation\Web\ControladorWeb;

(new ControladorWeb(new Aplicacao()))->tratar();
