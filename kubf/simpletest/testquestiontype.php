<?php
/**
 * Unit tests for this question type.
 *
 * @copyright &copy; 2018 LU,Zheng
 * @author luzheng27@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ku_questiontypes
 *//** */
    
require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG;
require_once($CFG->libdir . '/simpletestlib.php');
require_once($CFG->dirroot . '/question/type/ku_bf/questiontype.php');

class ku_bf_qtype_test extends UnitTestCase {
    var $qtype;
    
    function setUp() {
        $this->qtype = new ku_bf_qtype();
    }
    
    function tearDown() {
        $this->qtype = null;    
    }

    function test_name() {
        $this->assertEqual($this->qtype->name(), 'ku_bf');
    }
    
    // TODO write unit tests for the other methods of the question type class.
}

?>
