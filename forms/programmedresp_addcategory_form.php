<?php

require_once($CFG->dirroot . '/lib/formslib.php');

class programmedresp_addcategory_form extends moodleform {

    function definition() {
        

        $this->_form->addElement('text', 'name', get_string('name'));

        // _customdata['categories'] is an associative array where key is the id of the category and the value is the name.
        $categorySelect= $this->_form->createElement('select', 'parent', get_string('parentcategory', 'qtype_programmedresp'));
        // Github #32 => Disable the chance to create sub categories as far as we'll find out a way to back up them.
        $categorySelect->addOption(get_string('root', 'qtype_programmedresp'), 0);
        foreach ($this->_customdata['categories'] as $id => $name) {
            $categorySelect->addOption($name, $id, array('disabled' => 'disabled'));
        }
        $this->_form->addElement($categorySelect);

        $this->_form->addElement('hidden', 'action', 'addcategory');
        $this->_form->setType('action', PARAM_ALPHANUM);

        $this->_form->addRule('name', null, 'required', null, 'client');
        $this->_form->setType('name', PARAM_TEXT);

        $this->add_action_buttons();
    }

}
