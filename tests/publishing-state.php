<?php
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
use Systopic\Cms\Pages\PublishingState as P;
$now=new DateTimeImmutable('2026-11-01 12:00:00 UTC');
foreach ([[false,false,true,null,null,'missing'],[true,true,true,null,null,'trashed'],[true,false,false,null,null,'offline'],[true,false,true,$now->modify('+1 second'),null,'scheduled'],[true,false,true,null,$now->modify('-1 second'),'expired'],[true,false,true,$now,$now,'online']] as [$exists,$trash,$enabled,$from,$to,$key]) {if(P::of($exists,$trash,$enabled,$from,$to,$now)['key']!==$key)throw new Exception($key);}
echo "PASS: publishing states, precedence and exact window boundaries\n";
