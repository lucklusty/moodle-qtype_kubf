<?php
/**
 * The editing form code for this question type.
 *
 * @copyright &copy; 2018 LU,Zheng
 * @author luzheng27@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ku_questiontypes
 */
require_once($CFG->dirroot . '/question/type/edit_question_form.php');


/**
 * Boolean function editing form definition.
 * 
 * See http://docs.moodle.org/en/Development:lib/formslib.php for information
 * about the Moodle forms library, which is based on the HTML Quickform PEAR library.
 */

class qtype_kubf_edit_form extends question_edit_form {
    const  FACTOR_COUNT = 10;
    protected function definition_inner($mform) {
        $menu = array(
            get_string('caseno', 'qtype_kubf'),
            get_string('caseyes', 'qtype_kubf')
        );
        $mform->addElement('select', 'usecase', get_string('casesensitive', 'qtype_kubf'), $menu);
        $mform->addElement('text', 'functionname', get_string('functionname', 'qtype_kubf'),array('size' => 5));
        $mform->setType('functionname', PARAM_RAW_TRIMMED);
        $this->add_per_answer_fields($mform, get_string('answerno', 'qtype_kubf', '{no}'), question_bank::fraction_options());
        $this->add_interactive_settings();
    }
	protected function get_per_answer_fields($mform, $label, $gradeoptions,&$repeatedoptions, &$answersoption) {
		$repeated = array();
        $answeroptions = array();
		for($i = 0;$i<qtype_kubf_edit_form::FACTOR_COUNT;$i++){
			$answeroptions[0] = $mform->createElement('text', 'factor'.$i, get_string('factor', 'qtype_kubf', $i+1), array('size' => 20));
            $mform->setType('factor'.$i, PARAM_RAW_TRIMMED);
			$answeroptions[1] = $mform->createElement('select', 'score'.$i,get_string('grade'), $gradeoptions);
            $mform->setType('score'.$i, PARAM_RAW_TRIMMED);
			if($i == 0)
        		$repeated[] = $mform->createElement('group', 'answeroptions',$label, $answeroptions, null, false);
			else
				$repeated[] = $mform->createElement('group', 'answeroptions','', $answeroptions, null, false);
		}
        $menu = array(
            get_string('dnf', 'qtype_kubf'),
            get_string('cnf', 'qtype_kubf')
        );
        $repeated[] = $mform->createElement('select', 'functiontype', get_string('functiontype', 'qtype_kubf'), $menu);
		$repeated[] = $mform->createElement('editor', 'feedback',
                get_string('feedback', 'question'), array('rows' => 5), $this->editoroptions);

        $answersoption = 'answers';

        return $repeated;
    }
	protected function data_preprocessing($question) {
		$question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question);
        return $question;
    }
	protected function data_preprocessing_answers($question, $withanswerfiles = false) {
        $question = parent::data_preprocessing_answers($question, $withanswerfiles);
        //if (empty($question->options->answers)) {
        return $question;
        //}
	}
    function set_data($question) {
        // TODO, preprocess the question definition so the data is ready to load into the form.
        // You may not need this method at all, in which case you can delete it.

        // For example:
        // if (!empty($question->options)) {
        //     $question->customfield = $question->options->customfield;
        // }
        $i = 0;
        $j = 0;
        if(isset($question->options->answers)){
            foreach($question->options->answers as $key=>$value){
                $j = 0;
                foreach($question->options->answers[$key]->factorandscore as $key1=>$value1){
                    $question->usecase = $value1->usecase;
                    $tmp1 = 'factor'.$j.'['.$i.']';
                    $tmp2 = 'score'.$j.'['.$i.']';
                    $tmp3 = 'functiontype'.'['.$i.']';
                    $question->$tmp1 = $value1->term;
                    $question->$tmp2 = $value1->score;
                    $question->$tmp3 = $value1->functiontype;
                    $j++;
                }
                $i++;
            }
        }
        parent::set_data($question);
    }

    function validation($data, $files) {
        $errors = array();
        //$errors["answeroptions[0]"]= 'test';
        // TODO, do extra validation on the data that came back from the form. E.g.
        // if (/* Some test on $data['customfield']*/) {
        //     $errors['customfield'] = get_string( ... );
        // }

        if ($errors) {
            return $errors;
        } else {
            return true;
        }
    }

    function qtype() {
        return 'kubf';
    }
}

?>