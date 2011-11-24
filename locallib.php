<?php

/**
 * cwqueue local lib
 *
 * @package   local_cwqueue
 * @copyright 2011 Sun Zhigang (http://sunner.cn)
 * @author    Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

define('BASE_NUMBER', 1000); //起始排号
define('CURRENT_TIME_INTERVAL', 330);  // 判断当前时间用

/**
 * 将时间整理为矢量化的服务时间，即0-7:30
 */
function cwq_vectorize_time($time) {
    $hour = date('G', $time);
    $minute = date('i', $time);
    if (($hour == 13 and $minute >= 30) or $hour > 13) {
        $hour -= 2;
    }
    $hour -= 8;

    return $hour * 60 + $minute;
}

function cwq_actual_time($minutes, $date = -1) {
    if ($date === -1) {
        $date = time();
    }
    $time = new DateTime();
    $time->setTimestamp($date);

    if ($minutes > 210) {
        $minutes += 120;  // 加上午休时间
    }
    $time->setTime((int)($minutes / 60) + 8, $minutes % 60);

    return $time->getTimestamp();
}

/**
 * 是否工作时间
 */
function cwq_is_working($time = -1) {
    if ($time === -1) {
        $time = time();
    }

    $t = date('Gi', $time);

    return 800 <= $t and $t <= 1630;
}

/**
 * 是否是午休时间
 */
function cwq_is_breaking($time = -1) {
    if ($time === -1) {
        $time = time();
    }

    $t = date('Gi', $time);
    return 1130 <= $t and $t <= 1330;
}

/**
 * 是否是办公时间
 */
function cwq_is_serving($time = -1) {
    if ($time === -1) {
        $time = time();
    }

    return cwq_is_working($time) and !cwq_is_breaking($time);
}

/**
 * 排队机是否在工作
 */
function cwq_queue_is_working() {
    global $DB;

    $select = '? - time < 7800'; // 130分钟内有数据视为在工作
    $c = $DB->count_records_select('cwqueue_status', $select, array(time()));
    return $c != 0;
}

/**
 * 判断$time是否是当前时间
 *
 * 当前时刻和$time相差不超过CURRENT_TIME_INTERVAL
 */
function cwq_is_current($time) {
    return (time() - $time) < CURRENT_TIME_INTERVAL;
}

/**
 * 返回当前排队状态
 *
 * 如果当前时间无合适状态，返回false
 */
function cwq_current_status() {
    global $DB;

    $rs = $DB->get_records('cwqueue_status', null, 'time DESC', '*', 0, 1);
    $r = reset($rs);

    if (cwq_is_current($r->time)) {
        return $r;
    }

    return false;
}

/**
 * 指定时间段内的统计数据
 *
 * 从$until时刻开始，向前$back分钟。 back必须在一天之内
 *
 * 返回：
 *   object->starttime  真正开始处理时间
 *   object->endtime    真正结束处理时间
 *   object->count      处理人数
 *   object->new        新增人数
 */
function cwq_serve_statistics($until, $back = 1440) {
    global $DB;

    $params['year']     = date('Y', $until);
    $params['month']    = date('N', $until);
    $params['day']      = date('j', $until);
    $params['tominutes'] = cwq_vectorize_time($until);
    $params['fromminutes'] = $params['tominutes'] - $back > 0 ? $params['tominutes'] - $back : 0;

    // 获得开始时刻记录
    $select = 'year = :year AND month = :month AND day = :day AND minutes >= :fromminutes';
    $rs = $DB->get_records_select('cwqueue_status', $select, $params, 'time ASC', '*', 0, 1);
    if (empty($rs)) {
        return null;
    }
    $first = reset($rs);

    // 获得结束时刻记录
    $select = 'year = :year AND month = :month AND day = :day AND minutes <= :tominutes';
    $rs = $DB->get_records_select('cwqueue_status', $select, $params, 'time DESC', '*', 0, 1);
    if (empty($rs)) {
        return null;
    }
    $last = reset($rs);

    $ret = new stdClass();
    $ret->starttime = $first->minutes;
    $ret->endtime = $last->minutes;
    $ret->count = $last->current - $first->current;
    $ret->new = $last->last - $first->last;
    $ret->current = $last->current;
    $ret->last = $last->last;

    return $ret;
}

/**
 * 预测number的服务时间
 *
 * @param number - 排号
 * @param at - 发起预测动作的时刻，也是要预测的日期
 */
function cwq_forecast($number, $at = -1) {
    if ($at === -1) {
        $at = time();
    }

    $ret = last_hour_oracle::forecast_serve_time($number, $at);

    $current_status = cwq_current_status();
    $ret->served = $number <= $current_status->current;

    return $ret;
}

/**
 * 时间预测基类
 */
class oracle {
    /**
     * 预测服务时间
     *
     * @param number - 排号
     * @param at - 发起预测动作的时刻，也是要预测的日期
     * @return object - $o->begin, 时间段起始时刻；$o->end, 时间段结束
     */
    static public function forecast_serve_time($number, $at) {
        return null;
    }
}

class last_hour_oracle extends oracle {

    static public function forecast_serve_time($number, $at) {
        if (!$lasthour = cwq_serve_statistics($at, 60)) {
            return null;
        }
        if ($lasthour->endtime == $lasthour->starttime) {  // Can not calc speed
            return null;
        }

        $ret = new stdClass();
        $speed = $lasthour->count / ($lasthour->endtime - $lasthour->starttime);
        $minutes = $lasthour->endtime + ($number - $lasthour->current) / $speed;
        $ret->begin = cwq_actual_time($minutes - 5, $at);
        $ret->end = cwq_actual_time($minutes + 5, $at);

        return $ret;
    }
}
