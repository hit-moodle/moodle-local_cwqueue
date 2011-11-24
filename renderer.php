<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
//                      Online Judge for Moodle                          //
//        https://github.com/hit-moodle/moodle-local_onlinejudge         //
//                                                                       //
// Copyright (C) 2009 onwards  Sun Zhigang  http://sunner.cn             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * online judge render class
 *
 * @package   local_onlinejudge
 * @copyright 2011 Sun Zhigang (http://sunner.cn)
 * @author    Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');
require_once("$CFG->libdir/formslib.php");

/**
 * cwqueue renderer class
 */
class local_cwqueue_renderer extends plugin_renderer_base {

    function today() {
        $output = '';

        $output .= $this->current();

        // 今日统计
        $now = time();
        if ($today = cwq_serve_statistics($now) and $lasthour = cwq_serve_statistics($now, 60)) {
            $interval = $today->endtime - $today->starttime;
            $hour_average = round($interval ? $today->count * 60 / $interval : 0);
            $output .= $this->box("今天平均每小时办理{$hour_average}笔，最近".($lasthour->endtime-$lasthour->starttime)."分钟办理了{$lasthour->count}笔");
        }

        return $output;
    }

    function current() {
        $output = '';

        if ($status = cwq_current_status()) {
            $output = $this->box('现已办理到第'.($status->current + BASE_NUMBER).'号，还有'.($status->last - $status->current).'人在等待');
        }

        return $output;
    }

    function forecast() {
        $output = '';

        $form = new forecast_form();
        $form->get_data(); // trigger the validation()
        $form->display();  // result will be displayed in the form if any
        return $output;
    }

    function notice() {
        return $this->notification('这只是一个业余兴趣项目，本站不对它造成的任何结果负有责任');
    }
}

class forecast_form extends moodleform {
    protected $result_element = null;

	function definition() {
        $mform =& $this->_form;
		$mform->addElement('header', 'desc', '预测排队时间');
        $this->result_element = $mform->addElement('html', '');  // html elements have no ids, so we must store it for further reference
		$mform->addElement('text', 'number', '号码', array('size' => 10));
        $mform->addRule('number', '必须输入', 'required');
        $mform->setType('number', PARAM_INT);
        $this->add_action_buttons(false, '开始预测');
    }

    function definition_after_data(){
        // 如果已经有号码，显示结果
        $mform = $this->_form;
        $number = $mform->getElementValue('number');
        if (!empty($number) and $number > BASE_NUMBER and $serve = cwq_forecast($number - BASE_NUMBER)) {
            if ($serve->served) {
                $result_html = $number.'号已经办理或已过号';
            } else {
                $result_html = '预测'.$number.'号将在'.date('G:i', $serve->begin).'到'.date('G:i', $serve->end).'之间办理';
            }
            $this->result_element->_text = $result_html;
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['number'] - BASE_NUMBER <= 0) {
            $errors['number'] = '无效号码';
        }
        return $errors;
    }
}
