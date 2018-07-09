<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The question class code for this question type kubf.
 *
 * @copyright &copy; 2018 LU,Zheng
 * @author luzheng27@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ku_questiontypes
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');

/**
 * Represents a kubf question.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_kubf_question extends question_graded_by_strategy
    implements question_response_answer_comparer {
    /** @var boolean whether answers should be graded case-sensitively. */
    public $usecase;
    /** @var array of question_answer. */
    public $answers = array();

    public function __construct() {
        parent::__construct(new question_first_matching_answer_grading_strategy($this));
    }

    public function get_expected_data() {
        return array('answer' => PARAM_RAW_TRIMMED);
    }

    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response) &&
            ($response['answer'] || $response['answer'] === '0');
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseenterananswer', 'qtype_kubf');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank(
            $prevresponse, $newresponse, 'answer');
    }

    public function get_answers() {
        return $this->answers;
    }

    public function compare_response_with_answer(array $response, question_answer $answer) {
        if (!array_key_exists('answer', $response) || is_null($response['answer'])) {
            return false;
        }
        else{
            $response_factors = $this->get_response_factors($answer->factorandscore[0]->functiontype,$response['answer']);

            if(count($answer->factorandscore) == count($response_factors)){
                $count = 0;
                foreach($answer->factorandscore as $factor){
                    foreach($response_factors as $response_factor){
                        if($this->comparefactors($factor->term,$factor->functiontype,$response_factor,$factor->usecase)){
                            $count++;
                        }
                    }
                }
                if($count == count($answer->factorandscore))
                    return true;
                else
                    return false;
            }
            else
                return false;

        }

    }
    public function get_response_factors($functiontype,$factors){
        if($functiontype == 0) {
            $pattern = '/\s*\++\s*/';
            $factors = preg_replace($pattern,'@',$factors);
            $response_factors = explode('@', $factors);
            $response_factors = array_filter($response_factors);
            for($i = 0;$i < count($response_factors);$i++){
                $response_factors[$i] = trim($response_factors[$i]);
            }
        }
        else {
            $pattern = '/\)\s*\**\s*\(/';
            $factors = preg_replace($pattern,'@',$factors);
            $response_factors = explode('@', $factors);
            $response_factors = array_filter($response_factors);
            for($i = 0;$i < count($response_factors);$i++){
                $response_factors[$i] = str_replace('(','',$response_factors[$i]);
                $response_factors[$i] = str_replace(')','',$response_factors[$i]);
                $response_factors[$i] = trim($response_factors[$i]);
            }
        }
            return $response_factors;
    }
    public function get_score_from_answer(array $response,question_answer $answer){
        if (!array_key_exists('answer', $response) || is_null($response['answer'])) {
            return 0;
        }
        else{
            $response_factors = $this->get_response_factors($answer->factorandscore[0]->functiontype,$response['answer']);
            $score = 0;
            foreach($answer->factorandscore as $factor){
                foreach($response_factors as $response_factor){
                    if($this->comparefactors($factor->term,$factor->functiontype,$response_factor,$factor->usecase)){
                        $score = $score + $factor->score;
                    }
                }
            }
            if(count($response_factors) > count($answer->factorandscore)){
                $redurant = 1 / count($answer->factorandscore) * (count($response_factors)-count($answer->factorandscore));
                $score = ($score - $redurant) > 0 ? ($score - $redurant) : 0;
            }
            return $score;
        }
    }

    /**
     * Normalise a UTf-8 string to FORM_C, avoiding the pitfalls in PHP's
     * normalizer_normalize function.
     * @param string $string the input string.
     * @return string the normalised string.
     */
    protected static function safe_normalize($string) {
        if ($string === '') {
            return '';
        }

        if (!function_exists('normalizer_normalize')) {
            return $string;
        }

        $normalised = normalizer_normalize($string, Normalizer::FORM_C);
        if (is_null($normalised)) {
            // An error occurred in normalizer_normalize, but we have no idea what.
            debugging('Failed to normalise string: ' . $string, DEBUG_DEVELOPER);
            return $string; // Return the original string, since it is the best we have.
        }

        return $normalised;
    }

    public function get_correct_response() {
        $response = parent::get_correct_response();
        if ($response) {
            $response['answer'] = $this->clean_response($response['answer']);
        }
        return $response;
    }

    public function clean_response($answer) {
        // Break the string on non-escaped asterisks.
        $bits = preg_split('/(?<!\\\\)\*/', $answer);

        // Unescape *s in the bits.
        $cleanbits = array();
        foreach ($bits as $bit) {
            $cleanbits[] = str_replace('\*', '*', $bit);
        }

        // Put it back together with spaces to look nice.
        return trim(implode(' ', $cleanbits));
    }

    public function check_file_access($qa, $options, $component, $filearea,
                                      $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $this->get_matching_answer(array('answer' => $currentanswer));
            $answerid = reset($args); // Itemid is answer id.
            return $options->feedback && $answer && $answerid == $answer->id;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                $args, $forcedownload);
        }
    }
    public function grade_response(array $response) {
        $answer = $this->get_matching_answer($response);
        if ($answer) {
            return array($answer->score,
                question_state::graded_state_for_fraction($answer->score));
        } else {
            return array(0, question_state::$gradedwrong);
        }
    }
    public function get_matching_answer(array $response) {
        $select_right_answer = new stdClass();
        $score = 0;
        foreach($this->answers as $right_answer){
                $is_right = $this->compare_response_with_answer($response, $right_answer);
                if($is_right){
                    $select_right_answer = $right_answer;
                    $score = 1;
                    break;
                }else{
                    $fraction_new = $this->get_score_from_answer($response,$right_answer);
                    if($score <= $fraction_new){
                        $score = $fraction_new;
                        $select_right_answer = $right_answer;
                    }
                }
            }
        $select_right_answer->score = $score;
        return $select_right_answer;
    }
    function comparefactors($factor1,$functiontype,$factor2,$usecase){
        if($functiontype == 0)
            return $this->comparednffactors($factor1,$factor2,$usecase);
        else
            return $this->comparecnffactors($factor1,$factor2,$usecase);
    }
    function comparednffactors($factor1,$factor2,$usecase){
        if($usecase == 0){
            $factor1 = mb_convert_case($factor1, MB_CASE_UPPER, 'UTF-8');
            $factor2 = mb_convert_case($factor2, MB_CASE_UPPER, 'UTF-8');
        }
        $factor1 = trim($factor1);
        $factor2 = trim($factor2);
        $array1 = explode('*',trim($factor1));
        if(count($array1) == 1){
            $array1 = str_split($factor1);
        }
        for($i = 0; $i<count($array1)-1;$i++) {
            if ($array1[$i + 1] == '\'') {
                $array1[$i] = $array1[$i] . $array1[$i + 1];
                $array1[$i + 1] = '';
            }
        }

        $array2 = str_split($factor2);
        for($i = 0; $i<count($array2)-1;$i++) {
            if ($array2[$i + 1] == '\'') {
                $array2[$i] = $array2[$i] . $array2[$i + 1];
                $array2[$i + 1] = '';
            }
        }
        sort($array1);
        sort($array2);
        $tep1 = implode($array1);
        $tep2 = implode($array2);
        if($tep1 ==$tep2)
            return true;
        else
            return false;
    }
    function comparecnffactors($factor1,$factor2,$usecase){
        if($usecase == 0){
            $factor1 = mb_convert_case($factor1, MB_CASE_UPPER, 'UTF-8');
            $factor2 = mb_convert_case($factor2, MB_CASE_UPPER, 'UTF-8');
        }

        $array1 = explode('+',$factor1);
        $array2 = explode('+',$factor2);
        if(count($array1)!=count($array2))
            return false;
        else{
            for($i = 0 ;$i<count($array1);$i++){
                $array1[$i] = trim($array1[$i]);
            }
            for($i = 0 ;$i<count($array2);$i++){
                $array2[$i] = trim($array2[$i]);
            }
            sort($array1);
            sort($array2);
            $tep1 = implode($array1);
            $tep2 = implode($array2);
            if($tep1 ==$tep2)
                return true;
            else
                return false;
        }

    }
}
class qtype_kubf_answer extends question_answer{

    public $factorandscore = array();

    public function __construct($id, $answer, $fraction, $feedback, $feedbackformat, $factorandscore)
    {
        parent::__construct($id, $answer, $fraction, $feedback, $feedbackformat);
        $this->factorandscore = $factorandscore;
    }
}
