<?php
/**
 * The question type class for the Boolean function question type.
 *
 * @since      Moodle 2.0
 * @package    ku_questiontypes
 * @copyright &copy; 2018 Zheng Lu
 * @author 	   luzheng27@gamil.com
 *//** */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/kubf/question.php');
/**
 * The Boolean function question class
 *
 *
 */
class qtype_kubf extends question_type {
    const  FACTOR_COUNT = 10;
    function name() {
        return 'kubf';
    }

    // TODO think about whether you need to override the is_manual_graded or
    // is_usable_by_random methods form the base class. Most the the time you
    // Won't need to.

    /**
     * @return boolean to indicate success of failure.
     */
    public function get_question_options($question) {
        global $CFG, $DB, $OUTPUT;
        parent::get_question_options($question);
        $factors_and_scores = $DB->get_records_sql(
            "SELECT k.* " .
            "FROM {qtype_kubf_options} k " .
            "WHERE k.question_id = ? " .
            "ORDER BY k.answer_id ASC", array($question->id));
        if (!isset($factors_and_scores) && count($factors_and_scores)==0 ) {
            echo $OUTPUT->notification('Error: Missing question answer for numerical question ' .
                $question->id . '!');
            return false;
        }
        else{
            foreach($question->options->answers as $key => $value){
                $question->functionname = $factors_and_scores;
                $faf = array();
                foreach($factors_and_scores as $key1 => $value1){
                    $question->functionname = $value1->functionname;
                    if($key == $value1->answer_id)
                        $faf[] = $value1;
                }
                $value->factorandscore = $faf;
                $value->functiontype = $faf[0]->functiontype;
            }
        }
        return true;
    }

    /**
     * Save the units and the answers associated with this question.
     * @return boolean to indicate success of failure.
     */
    function save_question_options($question) {
        global $DB;
        $context = $question->context;

        // Get old versions of the objects.
        $oldanswers = $DB->get_records('question_answers',
            array('question' => $question->id), 'id ASC');
        // Save the factors.
        $result = $this->save_factors($question);
        if (isset($result->error)) {
            return $result;
        } else {
            $factors = $result->factors;
            $answers = $result->answers;
        }
        // if this is a question, insert all the new answers.
        //else delete all old answers for this question, and insert again.
        if(count($oldanswers)!=0) {
            $this->delete_question($question->id,'');
        }
        for($i = 0;$i<count($answers);$i++){
                $id = $DB->insert_record('question_answers', $answers[$i]);
            for($j = $i * qtype_kubf::FACTOR_COUNT;$j < ($i + 1) * qtype_kubf::FACTOR_COUNT  ; $j++){
                $factors[$j]->answer_id = $id;
            }
        }
       // Insert all the new factors.
        for($i = 0;$i<count($factors);$i++){
            if(trim($factors[$i]->term) != '' && $factors[$i]->score != '0.0')
                $DB->insert_record('qtype_kubf_options',$factors[$i]);
        }

        return true;
    }
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $tmp_answers = array();
        foreach($questiondata->options->answers as $an){
            $tmp_answers[] = new qtype_kubf_answer($an->id,$an->answer, $an->fraction, $an->feedback, $an->feedbackformat, $an->factorandscore);
        }
        $question->answers = $tmp_answers;
    }



    /**
     * Deletes question from the question-type specific tables
     *
     * @param integer $questionid The question being deleted
     * @return boolean to indicate success of failure.
     */
    function delete_question($questionid,$contextid) {
        global $DB;
        $DB->delete_records('qtype_kubf_options',array('question_id'=>$questionid));
        $DB->delete_records('question_answers',array('question'=>$questionid));
        parent::delete_question($questionid, $contextid);
        return true;
    }
    public function get_random_guess_score($questiondata) {
        foreach ($questiondata->options->answers as $aid => $answer) {
            if ('*' == trim($answer->answer)) {
                return max($answer->fraction - $questiondata->options->unitpenalty, 0);
            }
        }
        return 0;
    }
    public function get_possible_responses($questiondata) {
        $responses = array();

        $unit = $this->get_default_numerical_unit($questiondata);

        $starfound = false;
        foreach ($questiondata->options->answers as $aid => $answer) {
            $responseclass = $answer->answer;

            if ($responseclass === '*') {
                $starfound = true;
            } else {
                $responseclass = $this->add_unit($questiondata, $responseclass, $unit);

                $ans = new qtype_numerical_answer($answer->id, $answer->answer, $answer->fraction,
                    $answer->feedback, $answer->feedbackformat, $answer->tolerance);
                list($min, $max) = $ans->get_tolerance_interval();
                $responseclass .= " ({$min}..{$max})";
            }

            $responses[$aid] = new question_possible_response($responseclass,
                $answer->fraction);
        }

        if (!$starfound) {
            $responses[0] = new question_possible_response(
                get_string('didnotmatchanyanswer', 'question'), 0);
        }

        $responses[null] = question_possible_response::no_response();

        return array($questiondata->id => $responses);
    }
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid) {
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }
    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
        // TODO create a blank repsonse in the $state->responses array, which    
        // represents the situation before the student has made a response.
        return true;
    }

    function restore_session_and_responses(&$question, &$state) {
        // TODO unpack $state->responses[''], which has just been loaded from the
        // database field question_states.answer into the $state->responses array.
        return true;
    }
    
    function save_session_and_responses(&$question, &$state) {
        // TODO package up the students response from the $state->responses
        // array into a string and save it in the question_states.answer field.
    
        $responses = '';
    
        return set_field('question_states', 'answer', $responses, 'id', $state->id);
    }
    
    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG;

        $readonly = empty($options->readonly) ? '' : 'disabled="disabled"';

        // Print formulation
        $questiontext = $this->format_text($question->questiontext,
                $question->questiontextformat, $cmoptions);
        $image = get_question_image($question, $cmoptions->course);
    
        // TODO prepare any other data necessary. For instance
        $feedback = '';
        if ($options->feedback) {
    
        }
    
        include("$CFG->dirroot/question/type/kubf/display.html");
    }
    
    function grade_responses(&$question, &$state, $cmoptions) {
        // TODO assign a grade to the response in state.
    }
    
    function compare_responses($question, $state, $teststate) {
        // TODO write the code to return two different student responses, and
        // return two if the should be considered the same.
        return false;
    }

    /**
     * Checks whether a response matches a given answer, taking the tolerance
     * and units into account. Returns a true for if a response matches the
     * answer, false if it doesn't.
     */
    function test_response(&$question, &$state, $answer) {
        // TODO if your code uses the question_answer table, write a method to
        // determine whether the student's response in $state matches the    
        // answer in $answer.
        return false;
    }

    function check_response(&$question, &$state){
        // TODO
        return false;
    }

    function get_correct_responses(&$question, &$state) {
        // TODO
        return false;
    }

    function get_all_responses(&$question, &$state) {
        $result = new stdClass;
        // TODO
        return $result;
    }

    function get_actual_response($question, $state) {
        // TODO
        $responses = '';
        return $responses;
    }

    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf,$preferences,$question,$level=6) {
        $status = true;

        // TODO write code to backup an instance of your question type.

        return $status;
    }

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {
        $status = true;

        // TODO write code to restore an instance of your question type.

        return $status;
    }

    /**
     * Save the answers and the options of kubf
     * @param $question
     */
    protected function save_factors($question){
        global $DB;
        $result = new stdClass();
        //factors store the info for table mdl_question_kubf_options.
        $factors = array();

        $tep_answers = array();
        $tep_score = array();
        $functionname = '';
        for($i = 0;$i < $question->noanswers; $i++){
            $functiontype = 0;
            for($j = 0;$j < qtype_kubf::FACTOR_COUNT;$j++){
                $tmp1 = 'factor'.$j;
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j] = new stdClass();
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j]->question_id = $question->id;
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j]->usecase = $question->usecase;
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j]->functionname = $question->functionname;
                $functionname = $question->functionname;
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j]->functiontype = $question->functiontype[$i];
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j]->term = $question->$tmp1[$i];
                $functiontype = $question->functiontype[$i];
                $tmp2 = 'score'.$j;
                $factors[$i*qtype_kubf::FACTOR_COUNT+$j]->score = $question->$tmp2[$i];
                if($question->$tmp1[$i]!=''){
                    if($functiontype == 0){
                        @$tep_answers[$i] =  $tep_answers[$i] . ' + ' . $question->$tmp1[$i];
                    }
                    else{
                        @$tep_answers[$i] =  $tep_answers[$i] . ')(' . $question->$tmp1[$i];
                    }


                }
                if($question->$tmp2[$i]!='0.0'){
                    @$tep_score[$i] = $tep_score[$i] + $question->$tmp2[$i];
                }
            }
            if(isset($tep_answers[$i])) {
                if ($functiontype == 0)
                    @$tep_answers[$i] = substr($tep_answers[$i], 3);
                else
                    @$tep_answers[$i] = substr($tep_answers[$i], 1) . ')';
            }
        }
        ksort($factors );
        //$answers store the info for table mdl_question_answsers.
        $answers = array();
        for($i = 0;$i < count($tep_answers); $i++){
            $answers[$i] = new stdClass();
            $answers[$i]->question  = $question->id;
            $answers[$i]->answer = $functionname .' = '. $tep_answers[$i];
            $answers[$i]->answerformat = 0;
            $answers[$i]->score = $tep_score[$i];
            $answers[$i]->feedback = $question->feedback[$i]['text'];
            $answers[$i]->feedbackformat = $question->feedback[$i]['format'];
        }
        $result->answers = $answers ;
        $result->factors = $factors;
        return $result;
    }
}
class qtype_kubf_answer_processor {

}


?>
