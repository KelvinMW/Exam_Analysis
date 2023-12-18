<?php
// Include the Gibbon class
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Module\ExamAnalysis\Forms\BindValues;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\Timetable\CourseGateway;
use Gibbon\Domain\System\AlertLevelGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Http\Url;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Gibbon\Forms\Input\Button;
use Gibbon\Forms\Input\Input;
use Gibbon\Domain\FormGroups\FormGroupGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Forms\FormFactory;

require_once __DIR__ . '/moduleFunctions.php';
if(isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php')==false){
    $page->addError('You do not have permission to access this page.');
    $page->addError('Please contact your system administrator if you require access to this page.');
} else {
    $settingGateway = $container->get(SettingGateway::class);
    $form = Form::create('action', $session->get('absoluteURL') . '/index.php?q=/modules/Exam Analysis/meanDeviation.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('q', '/modules/Exam Analysis/meanDeviation.php');
    $form->addHiddenValue('gibbonSchoolYearID', $session->get('gibbonSchoolYearID'));
        //select exam type
        $exam_type = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
        $row = $form->addRow();
        $row->addLabel('exam_type[]', __('Select Exam Type'))
            ->description(__('Select the type of exam'))
            ->required();
        $row->addSelect('exam_type[]')->fromString($exam_type)->selectMultiple()->required()->placeholder();
    
        //select form group
        $row = $form->addRow()->addClass('course bg-blue-100');
        $row->addLabel('gibbonFormGroupID', __('Form Group'));
        $gibbonFormGroupID = array();
        $row->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->required()->selectMultiple()->selected($gibbonFormGroupID)->placeholder();
    
        //active courses for the current school year
        $data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
        //sql for courses
        $sql = "SELECT gibbonCourseID as value, name as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
        $row = $form->addRow()->addClass('course bg-blue-100');
        $row->addLabel('courses[]', __('Select Courses'));
        $selected = array();
        $row->addSelect('courses[]')->fromQuery($pdo, $sql, $data)
            ->selectMultiple()
            ->setSize(6)
            ->required()
            ->selected($selected);
        $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($gibbon->session);
    
        echo $form->getOutput();
    
}
?>