<?php
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Module\ExamAnalysis\Forms\BindValues;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\School\FacilityGateway;

use Gibbon\Domain\System\AlertLevelGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;


// Module includes
require_once __DIR__ . '/moduleFunctions.php';

//get alternative headers
$settingGateway = $container->get(SettingGateway::class);
$attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
$effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');

if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/analysis_view.php') == false){
    $page->addError(__('You do not have access to this action.'));
}
else{
    $form = Form::create('examAnalysis', $session->get('absoluteURL').'/index.php','get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
//select analysis form
$row = $form->addRow();
$row->addLabel('examType', __('Select Exam Type'))
    ->description(__('Select the type of exam'));
$types = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
$row->addSelect('type')->fromString($types)->required()->placeholder();
// Get the form inputs from the user

}