<?php
/** Integration against a configured DEV database; every fixture is rolled back. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
use Systopic\Db\Connection;
use Systopic\Db\Scope;
use Systopic\Cms\Settings\TemplateEditor;
use Systopic\Cms\Queries\SettingTree\SettingTree;
use Systopic\Cms\Tables\Settings\Operator;
$config = getenv('FLUXO_TEMPLATE_TEST_CONFIG');
if (!$config || !str_starts_with(basename($config), 'DEV.')) throw new RuntimeException('Supply a DEV config through FLUXO_TEMPLATE_TEST_CONFIG.');
$settings = json_decode(file_get_contents($config), true, flags: JSON_THROW_ON_ERROR);
$connection = Connection::open($settings['db']);
$scope = new Scope($connection);
$pdo = $connection->pdo();
$pdo->beginTransaction();
try {
    $prefix = 'QA_' . bin2hex(random_bytes(4)) . '_';
    $definitions = [];
    foreach (TemplateEditor::KINDS as $kind => $info) {
        $d = TemplateEditor::create($scope, null, $kind, $prefix . $kind);
        $definitions[$kind] = $d;
        check($kind . ' has the correct control and datatype', $d->type === $info[2] && $d->datatype === $info[3]);
        check($kind . ' is persisted and reopenable', SettingTree::byNodeId(new Scope($connection), $d->nodeId)?->name === $d->name);
        if (in_array($kind, ['text', 'number', 'decimal', 'switch'])) {
            check($kind . ' creates its hidden value automatically', count($d->options) === 1 && $d->options[0]->datatype === $d->datatype);
        }
    }
    $choice = $definitions['choice'];
    $first = TemplateEditor::create($scope, $choice, 'text', 'Left');
    $choice = SettingTree::byNodeId($scope, $choice->nodeId);
    $second = TemplateEditor::create($scope, $choice, 'text', 'Left');
    check('choice options have stable distinct values', $first->type === 'option' && $first->name === 'left' && $second->name === 'left_2');
    $field = TemplateEditor::create($scope, $definitions['group'], 'decimal', 'Width');
    check('group child stores its own number type', $field->type === 'input' && $field->datatype === 'decimal');
    $switch = $definitions['switch']; $key = $switch->name;
    $scope->save(Operator::assign($switch->setting, ['label' => 'Show image']));
    check('renaming keeps the template connection', SettingTree::byNodeId($scope, $switch->nodeId)->name === $key);
    rejects('blank names never create partial definitions', fn() => TemplateEditor::create($scope, null, 'switch', ' '));
    rejects('switch cannot acquire extra values', fn() => TemplateEditor::create($scope, $switch, 'text', 'Extra'));
    rejects('group rejects unsupported nested controls', fn() => TemplateEditor::create($scope, $definitions['group'], 'choice', 'Extra'));
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
echo "PASS: all DEV test rows rolled back\n";
