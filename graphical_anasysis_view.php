<?php
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Module\ExamAnalysis\Forms\BindValues;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\ExamAnalysis\Domain\TechGroupGateway;
use Gibbon\Module\ExamAnalysis\Domain\SubcategoryGateway;
use Gibbon\Domain\School\FacilityGateway;
require_once __DIR__ . '/moduleFunctions.php';
if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/index.php') == true) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $moduleName = $session->get('module');
    $page->breadcrumbs->add(__('Graphical Analysis'));
    echo "<h2>Graphical Analysis Page</h2>";
    echo '<iframe
    width="600"
    height="400"
    seamless
    frameBorder="0"
    scrolling="no"
    src="http://127.0.0.1:8088/superset/explore/p/Q8b1pY1pqXM/?standalone=1&height=400">
    </iframe><br/>';
    $form = Form::create('GraphicalAnalysis', $session->get('absoluteURL') . '/modules/' . $moduleName . '/name_addProccess.php', 'post');
    $form->setFactory(DatabaseFormFactory::create($pdo));     
    $form->addHiddenValue('address', $session->get('address'));
    
    $row = $form->addRow();
        $row->addLabel('issueName', __('Issue Subject'));
        $row->addTextField('issueName')
            ->required()
            ->maxLength(55);
    
    }

echo "<h2>Graphical Analysis Page</h2>";
?>