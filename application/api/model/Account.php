<?php
namespace app\api\model;
use think\Model;
use think\Db;
class Account extends Model{
    // protected $table = 'desc_order';
    function log_account_change($user_id, $user_money = 0, $frozen_money = 0, $rank_points = 0, $pay_points = 0, $change_desc = '', $change_type = ACT_OTHER, $order_type = 0, $deposit_fee = 0){
        $is_go = true;
        $is_user_money = 0;
        $is_pay_points = 0;
        if ($is_go && ($user_money || $frozen_money || $rank_points || $pay_points)) {
            $account_log = array('user_id' => $user_id, 'user_money' => $user_money, 'frozen_money' => $frozen_money, 'rank_points' => $rank_points, 'pay_points' => $pay_points, 'change_time' => time(), 'change_desc' => $change_desc, 'change_type' => $change_type, 'deposit_fee' => $deposit_fee);
            Db::name('account_log')->insert($account_log);
            $save = array();
            $fee = $user_money + $deposit_fee;
            $save['user_money'] = array('exp','user_money +'.$fee);
            $save['frozen_money'] = array('exp','frozen_money +'.$frozen_money);
            $save['rank_points'] = array('exp','rank_points +'.$rank_points);
            $save['pay_points'] = array('exp','pay_points +'.$pay_points);
            Db::name('users')->where('user_id = '.$user_id)->update($save);//更新账户各个余额
            $uinfo = Db::name('users')->field('rank_points')->where('user_id = '.$user_id)->find();
            $user_rank_points = $uinfo['rank_points'];//用户的排名积分

            $rank_row = Db::name('user_rank')->field('rank_id,discount')->where('special_rank = 0 AND min_points <= ' . $user_rank_points . ' AND max_points > ' . $user_rank_points)->find();
            if ($rank_row) {
                $rank_row['discount'] = $rank_row['discount'] / 100;
            }else {
                $rank_row['discount'] = 1;
                $rank_row['rank_id'] = 0;
            }
            //更新会员的等级信息
            $save = array();
            $save['user_rank'] = $rank_row['rank_id'];
            Db::name('users')->where('user_id = '.$user_id)->update($save);
            $save['discount'] = $rank_row['discount'];
            Db::name('sessions')->where('userid = '.$user_id.' AND adminid = 0')->update($save);
        }
    }
}