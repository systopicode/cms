<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
use Systopic\Cms\Pages\RelationRules as Rules;
$graph = [
    10 => ['original'=>0,'redirect'=>0,'parent'=>0],
    11 => ['original'=>10,'redirect'=>0,'parent'=>0],
    12 => ['original'=>0,'redirect'=>11,'parent'=>0],
    13 => ['original'=>0,'redirect'=>0,'parent'=>10],
    14 => ['original'=>0,'redirect'=>15], 15=>['original'=>14,'redirect'=>0],
    16 => ['original'=>0,'redirect'=>0,'trashed'=>true],
    17 => ['type'=>'media'], 18=>['type'=>'site'],
    19 => ['type'=>'page','parent'=>18],
];
$node = static fn(int $id): ?array => $graph[$id] ?? null;
Rules::target(10,13,$node);
check('unrelated redirect allowed', true);
rejects('self-reference rejected', fn()=>Rules::target(10,10,$node));
rejects('indirect mixed alias/redirect cycle rejected', fn()=>Rules::target(10,12,$node));
rejects('already cyclic target graph rejected', fn()=>Rules::target(10,14,$node));
rejects('missing target rejected', fn()=>Rules::target(10,99,$node));
rejects('trashed target rejected', fn()=>Rules::target(10,16,$node));
rejects('non-page target rejected', fn()=>Rules::target(10,17,$node));
rejects('alias under own original rejected', fn()=>Rules::placement(10,10,$node));
rejects('alias under descendant rejected', fn()=>Rules::placement(10,13,$node));
rejects('alias under another alias of original rejected', fn()=>Rules::placement(10,11,$node));
rejects('cyclic original rejected even at root', fn()=>Rules::placement(14,0,$node));
Rules::placement(10,0,$node);
Rules::placement(10,19,$node);
check('root and independent site destinations allowed',true);
echo "All page-relation checks passed.\n";
