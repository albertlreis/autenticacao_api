<?php
namespace App\Saas;
final class ControlRejected extends \RuntimeException {
 public function __construct(public \Symfony\Component\HttpFoundation\Response $response){parent::__construct('Control operation rejected.');}
}
