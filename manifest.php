<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http:// www.gnu.org/licenses/>.
*/

// This file describes the module, including database tables

// Basic variables
$name        = 'Exam Analysis';            // The name of the module as it appears to users. Needs to be unique to installation. Also the name of the folder that holds the unit.
$description = 'This module is for administrators to do exam analysis';            // Short text description
$entryURL    = "analysis_view.php";   // The landing page for the unit, used in the main menu
$type        = "Additional";  // Do not change.
$category    = 'Assess';            // The main menu area to place the module in
$version     = '1.2.00';            // Version number
$author      = 'Kelvin';            // Your name
$url         = 'https://github.com/KelvinMW';            // Your URL

/*/ Module tables & gibbonSettings entries
//$moduleTables[] = ''; // One array entry for every database table you need to create. Might be nice to preface the table name with the module name, to keep the db neat. 
//$moduleTables[] = ''; // Also can be used to put data into gibbonSettings. Other sql can be run, but resulting data will not be cleaned up on uninstall.
*/
// Add gibbonSettings entries
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES
    (NULL, 'Exam Analysis', 'analyse', 'Exam Analysis', 'Analyse different exams. Better.', '')";

// Action rows 
// One array per action
//Exam Analysis per exam type
$actionRows[] = [
    'name'                      => 'Exam Analysis', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Analysis', // Optional: subgroups for the right hand side module menu
    'description'               => 'This module is for administrators to do exam analysis', // Text description
    'URLList'                   => 'analysis_view.php', // List of pages included in this action
    'entryURL'                  => 'analysis_view.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];

//Exam Analysis: Mean score deviation per subject for selected exam type
$actionRows[] = [
    'name'                      => 'Mean Score Over Years', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Graphs', // Optional: subgroups for the right hand side module menu
    'description'               => 'This module is for administrators to do exam analysis', // Text description
    'URLList'                   => 'meanScoreOverYears.php', // List of pages included in this action
    'entryURL'                  => 'meanScoreOverYears.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built-in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built-in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built-in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built-in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built-in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];
//Exam Analysis: Mean score deviation per subject for selected exam type
$actionRows[] = [
    'name'                      => 'Mean Score Deviation', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Analysis', // Optional: subgroups for the right hand side module menu
    'description'               => 'This module is for administrators to do exam analysis', // Text description
    'URLList'                   => 'meanDeviation.php', // List of pages included in this action
    'entryURL'                  => 'meanDeviation.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built-in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built-in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built-in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built-in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built-in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];
// Action rows 

// Hooks 
//$hooks[] = ''; // Serialised array to create hook and set options. See Hooks documentation online.

?>