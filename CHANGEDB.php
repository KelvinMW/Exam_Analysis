<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql =array();
$count = 0;

// v0.0.00
$sql[$count][0] = '0.0.00';
$sql[$count][1] = "-- First version, nothing to update";


// v0.0.0x
//$count++;
//$sql[$count][0] = "0.0.0x";
//$sql[$count][1] = "-- One block for each subsequent version, place sql statements here for version, seperated by ;end";
//v1.1.00
$count++;
$sql[$count][0] = '1.1.00';
$sql[$count][1] ="
UPDATE INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES
        (NULL, 'Exam Analysis', 'analyse', 'Exam Analysis', 'Analyse different exams. Better.', '');end
UPDATE gibbonAction SET URLList='analysis_view.php' WHERE name='Exam Analysis' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Exam Ananysis');end"