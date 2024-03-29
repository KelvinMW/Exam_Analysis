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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Exam Analysis/name_edit.php')) {
	// Access denied
	$page->addError(__('You do not have access to this action.'));
} else {
    $ID = $_POST['ID']; // The ID / primary key param posted from the name_view page.

    // For a form
    // Check out https:// gist.github.com/SKuipers/3a4de3a323ab9d0969951894c29940ae for a cheatsheet / guide
    // Don't forget to use the posted ID and a query to be able to $form->loadAllValuesFrom($values);
}	
