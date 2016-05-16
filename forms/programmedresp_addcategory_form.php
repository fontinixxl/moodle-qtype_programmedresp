<?php

require_once($CFG->dirroot . '/lib/formslib.php');

class programmedresp_addcategory_form extends moodleform {

    function definition() {
        

        $this->_form->addElement('text', 'name', get_string('name'));
        $this->_form->addElement('select', 'parent', get_string('parentcategory', 'qtype_programmedresp'), $this->_customdata['categories']);
        $this->_form->addElement('hidden', 'action', 'addcategory');
        $this->_form->setType('action', PARAM_ALPHANUM);

        $this->_form->addRule('name', null, 'required', null, 'client');
        $this->_form->setType('name', PARAM_TEXT);

        $this->add_action_buttons();
    }

}
