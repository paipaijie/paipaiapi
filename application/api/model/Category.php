<?php
namespace app\api\model;
use think\Model;
use think\Db;
class Category extends Model{
    //分类展示
    public function catShow($cat_id=''){
        $where = array();
        //如果有分类id传进来，查询该分类下的分类信息，如果没有则查询所有
        if($cat_id != ''){
            $where['parent_id'] = $cat_id;
        }   
        $where['is_show'] = 1;
        $where['parent_id'] = 0;//先查询所有一级分类
        $catInfo = Db::name('category')->field('cat_id,cat_name,cat_icon,touch_icon')->where($where)->select();
        if(!empty($catInfo)){
            foreach ($catInfo as $key => $value) {
                $catInfo[$key]['touch_icon'] = SITE_URL.$value['touch_icon']; 
                //查询子分类
                $where['parent_id'] = $value['cat_id'];
                $sonCat = Db::name('category')->field('cat_id,cat_name,cat_icon,touch_icon')->where($where)->select(); 
                foreach ($sonCat as $k => $v) {
                    $sonCat[$k]['touch_icon'] = SITE_URL.$v['touch_icon'];    
                }
                $catInfo[$key]['sonCat'] = $sonCat;
            }
        }

        return empty($catInfo) ? array() : $catInfo;
    
    }

    
}