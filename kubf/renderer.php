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
 * The editing form code for this question type.
 *
 * @copyright &copy; 2018 LU,Zheng
 * @author luzheng27@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ku_questiontypes
 */

class qtype_kubf_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        $question = $qa->get_question();
        //$question->answers = $DB->get_records('question_answers', array('question' => $question->id), 'id ASC');
        $currentanswer = $qa->get_last_qt_var('answer');
        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
            'class' => 'form-control',
        );

        $feedbackimg = '';
        $fraction = 0;
        $select_right_answer = '';
        if ($options->correctness){
            foreach($question->answers as $right_answer){
                $is_right = $question->compare_response_with_answer(array('answer' => $currentanswer), $right_answer);
                if($is_right){
                    $fraction = 1;
                    $select_right_answer = $right_answer;
                    break;
                }else{
                    $fraction_new = $question->get_score_from_answer(array('answer' => $currentanswer),$right_answer);
                    if($fraction <= $fraction_new){
                        $fraction = $fraction_new;
                        $select_right_answer = $right_answer;
                    }
                }
            }

            $inputattributes['class'] .= ' ' . $this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
        }
        $qa->selected_right_answer =  $select_right_answer;
        $questiontext = $question->format_questiontext($qa);


        $input = html_writer::empty_tag('input', $inputattributes) . $feedbackimg;
        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));


        $result .= html_writer::start_tag('div', array('class' => 'ablock form-inline'));
        $result .= html_writer::tag('label', get_string('answercolon', 'qtype_kubf').$question->answers[0]->factorandscore[0]->functionname.' = ', array('for' => $inputattributes['id']));
        $result .= html_writer::tag('span', $input, array('class' => 'answer'));
        $result .= html_writer::end_tag('div');


        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer),
                    array('class' => 'validationerror')));
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $answer= $question->answers;
        $feedback = $question->format_text($answer[0]->feedback, $answer[0]->feedbackformat,
                    $qa, 'question', 'answerfeedback', $answer[0]->id);


        return $feedback;
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $answer = $qa->selected_right_answer;
        if (!$answer) {
            return '';
        }
        return get_string('correctansweris', 'qtype_kubf',
            s($question->clean_response($answer->answer)));
    }
}
