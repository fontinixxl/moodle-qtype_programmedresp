<?php 

require_once($CFG->dirroot.'/lib/formslib.php');

class programmedresp_addfunctions_form extends moodleform {
    
    function definition() {
        
    	$attrs = array('cols' => '45', 'rows' => '15');
        $this->_form->addElement('textarea', 'functionstextarea', get_string('functionstextarea', 'qtype_programmedresp'), $attrs);
        
        $this->_form->addElement('hidden', 'action', 'addfunctions');
        $this->_form->addElement('hidden', 'fcatid', $this->_customdata['fcatid']);
        
        $this->_form->addRule('functionstextarea', null, 'required', null, 'client');
        
        $this->add_action_buttons();
    }
}