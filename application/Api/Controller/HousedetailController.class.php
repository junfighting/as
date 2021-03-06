<?php
namespace Api\Controller;

use Common\Controller\AppframeController;

class HousedetailController extends AppframeController{
	
	protected $house_model;
	protected $housetype_model;
	protected $housephoto_model;
	protected $housedetail_model;
	protected $houseorder_model;
	protected $housesource_model;
    protected $city_model;
	protected $perpage = 20;
	
	public function _initialize() {
		parent::_initialize();
		$this->house_model=D("Common/House");
		$this->housetype_model=D("Common/Housetype");
		$this->housedetail_model=D("Common/Housedetail");
		$this->housephoto_model=D("Common/Housephoto");
        $this->houseorder_model=D("Common/Houseorder");
        $this->housesource_model=D("Common/Housesource");
		$this->city_model=D("Common/City");
	}

	public function index(){
	    //单价,名称,两室一厅,图片,入住人数,折扣,地图坐标,具体位置,优惠
        $field = 'as_house.houseid,as_house.tagid,as_house.housename,as_housedetail.price,as_house.typeid,as_house.housecity,as_housedetail.maxmembers,
                  as_housedetail.bedtype,as_housedetail.housearea,as_housedetail.discount,as_housedetail.bathroom,as_housedetail.mindays,as_housedetail.cash,
                  as_housedetail.starttime,as_housedetail.endtime,
                  as_housetype.housetype,as_house.houseintro,as_house.houseorder,as_house.houseaddress,as_house.house_x,as_house.house_y';
        $where = array();
        $houseid = I('request.houseid');
        if($houseid){
            $where['as_house.houseid'] = intval($houseid);
        }

        $cache = S(array('type'=>'file','prefix'=>'','expire'=>300));//'expire'=>60
        $key = 'housedetail';
        $houseDetail = $cache->$key;
        if(!empty($houseDetail)){
            $houseDetail = $cache->$key;
        }else{
            $houseDetail = $this->house_model->houseDetail($where,$field);
            foreach ($houseDetail as $k=>$v){
                $discount = json_decode($v['discount'],true);
                $houseDetail[$k]['starttime'] = date('H:i',$v['starttime']);
                $houseDetail[$k]['endtime'] = date('H:i',$v['endtime']);

                //tagid
                $tag = $this->housesource_model->where(array('id'=>$houseDetail[$k]['tagid']))->field('typename')->find();
                $houseDetail[$k]['typename'] = $tag['typename'];
                $houseDetail[$k]['housediscount'] = $discount;
                for($i=0;$i<count($discount);$i++){
                    $houseDetail[$k]['longrent_discount'][$i] =  '满'.$discount[$i]['days'].'天打'.$discount[$i]['discount'].'折';
                }
                $photoinfo =  $this->housephoto_model->getPhotoByHouseid($houseDetail[$k]['houseid'],$field = 'savename,photopath,weight');
                $photoinfo =  array_values($photoinfo);
                foreach ($photoinfo as $p=>$v){
                    $servername = httpType().$_SERVER['SERVER_NAME'];
                    $uploadDir = $servername.__ROOT__.'/data/upload/';
                    $houseDetail[$k]['housephoto'][] = $uploadDir.$photoinfo[$p]['photopath'];
                }
            }
            //设置缓存
            $cache->$key = $houseDetail;
        }

        $data['data']['housedetail'] = $houseDetail[0];
        $this->ajaxData($data);
    }


    /**
     *下单页面
     * 未使用
     */
    public function houseOrder(){
	    //考虑并发,两人同时下单
	    //isorder=1预定 0未预定
        $houseid = I('request.houseid');
        $isOrder = 1;
        $where = array('houseid'=>$houseid);
        $updata = array('isorder'=>$isOrder);
        if(empty($houseid)){
            $data['code'] = -2;
            $data['msg'] = '参数有误!';
            $this->ajaxData($data);
        }else{
            $state = $this->house_model->where(array('houseid'=>$houseid))->field('isorder')->find();
            if($state['isorder'] == 1){
                $data['code'] = -3;
                $data['msg'] = '该房屋已被预定,请选择其他房屋!';
                $this->ajaxData($data);
            }
        }
        if(!empty($where)){
            $res = $this->house_model->where($where)->save($updata);
        }
        if(!empty($res)){
            $this->ajaxData();
        }else{
            $data['code'] = -1;
            $data['msg'] = 'fail';
            $this->ajaxData($data);
        }
    }



    /**
     * 选择预定时间
    */
    public function pickOrderTime(){
        $houseid = I('request.houseid');
        $where['houseid'] = $houseid;
        $field = 'houseid,price,isdiscount,discount,starttime,endtime,mindays,specialprice';
        $data = $this->housedetail_model->where($where)->field($field)->select();
        $usingDate = $this->houseorder_model->getOrderedHouseTime($houseid);
        foreach ($data as $k=>$v){
//            $days = (intval($data[$k]['endtime'])- intval($data[$k]['starttime']))/86400 + 1;
            $data[$k]['starttime'] = date('H:i',$data[$k]['starttime']);
            $data[$k]['endtime'] = date('H:i',$data[$k]['endtime']);
            $data[$k]['housediscount'] = json_decode($data[$k]['discount'],true);
            //特殊价格
            $data[$k]['specialprice'] = json_decode($data[$k]['specialprice'],true);
            foreach ($data[$k]['specialprice'] as $sp => $v){
                $spdate = date('Y-m-d',$data[$k]['specialprice'][$sp]['time']);
                $spsplit = explode('-',$spdate);
                $specialprice[$spdate] = array(
                    'using'=> in_array($spdate,$usingDate) ? 1 : 0,
                    'special'=>1,
                    'date'=> $spdate,
                    'year'=> $spsplit[0],
                    'month'=> $spsplit[1],
                    'day'=> $spsplit[2],
                    'price'=> $data[$k]['specialprice'][$sp]['money'],
                );
            }
            $data[$k]['specialprice'] = array_values($specialprice);
            //所有可预约时间==无限制
//            for($i=0; $i<$days; $i++){
//                $dates[] = date('Y-m-d', strtotime($data[$k]['starttime'])+(86400*$i));
//            }

//            for($i=0;$i< count($dates); $i++){
//                $dsplit = explode('-',$dates[$i]);
//                $ordertime[$dates[$i]] = array(
//                    'using'=>0,
//                    'special'=> 0,
//                    'date'=> $dates[$i],
//                    'year'=> $dsplit[0],
//                    'month'=> $dsplit[1],
//                    'day'=> $dsplit[2],
//                    'price'=> $data[$k]['price'],
//                );
//            }
//            $ordertime = array_merge($ordertime,$specialprice);

            //对已预约到日期做标注
            $usingDate = $this->houseorder_model->getOrderedHouseTime($houseid);

            $usingCount = count($usingDate);
            for($i=0; $i < $usingCount; $i++){
                $dsplit = explode('-',$usingDate[$i]);
                $ordertime2[$usingDate[$i]] = array(
                    'using'=>1,
                    'special'=> 0,
                    'date'=> $usingDate[$i],
                    'year'=> $dsplit[0],
                    'month'=> $dsplit[1],
                    'day'=> $dsplit[2],
                    'price'=> $data[$k]['price'],
                );
            }
            if(!empty($specialprice)){
                $finalDate = array_merge($ordertime2,$specialprice);
            }else{
                $finalDate = $ordertime2;
            }

            //已约标注结束
            $data[$k]['ordertime'] = array_values($finalDate);
        }

        $data['data']['houseorder'] = $data;
        $this->ajaxData($data);

    }


//    public function getOrderedHouseTime($houseid){
//        $field='houseid,checkin_time,checkout_time';
////        max(score)
//        $where['houseid'] = $houseid;
//        $orderTime = $this->houseorder_model->where($where)->field($field)->select();
//        foreach ($orderTime as $k=>$v){
//            $a[] = $this->dateList($orderTime[$k]['checkin_time'],$orderTime[$k]['checkout_time']);
//        }
//        $n = count($a);
//        $b = array();
//        for ($i = 0;$i<$n;$i++){
//            $num = count($a[$i]);
//            unset($a[$i][$num-1]);
//            $a[$i]['houseid'] = $houseid;
//        }
//
//        for ($i = 0;$i<1;$i++){
//            for($j=$n-1;$j>=0;$j--){
//                if($a[$j]['houseid'] == $a[$i]['houseid'] ){
//                    $b = array_merge($b,$a[$j]);
//                }
//            }
//            unset($b['houseid']);
//        }
//        return $b;
//    }

//    /**
//     * 根据开始时间和截止时间,遍历之间的日期
//     * return array(二维)
//    */
//    public function dateList($starttime,$endtime){
//
//        $days = (intval($endtime)- intval($starttime))/86400 + 1;
//        for($i=0; $i<$days; $i++){
//            $dates[] = date('Y-m-d', intval($starttime)+(86400*$i));
//        }
//        return $dates;
//    }


}