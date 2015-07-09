'use strict';

angular.module('dachuwang')
  .controller('EditCustomerController',['$scope','$state','$stateParams','$window','rpc', 'daChuDialog', function($scope,$state,$stateParams,$window,rpc, dialog) {
    //数据初始化
    var cus_id = $stateParams.customer_id;
    var former_address;
    $scope.is_uploaded = 0;
    $scope.userGeo = {
      lat : null,
      lng : null
    };
    $scope.isRequesting = false;
    //$scope的函数都绑定在func对象内
    $scope.func = {};
    $scope.urls = null;
    //加载用户数据
    (function(x) {
      var pdata = {id:x};
      rpc.load('customer/edit_input','POST',pdata).then(function(msg) {
        $scope.dOptions = msg.directions;
        $scope.oDimensions = msg.dimensions;
        //ng-options必须返回ng-repeat的对象
        $scope.dimensions = (function(t) {
          if(!t) {
            return null;
          }
          var i,len = $scope.oDimensions.length;
          for(i=0; i<len; i++) {
            if(t === $scope.oDimensions[i].value){
              return $scope.oDimensions[i];
            }
          }
          return null;
        })(msg.info.dimensions);
        $scope.direction = (function(dire) {
          if(!dire) {
            return null;
          }
          var i,len = $scope.dOptions.length;
          for(i=0; i<len; i++) {
            if($scope.dOptions[i].value == dire) {
              return $scope.dOptions[i];
            }
          }
        })(msg.info.direction);
        // 初始化图片
        $scope.urls = msg.info.pic_urls;
        //初始化客户类型
        $scope.types = msg.types;
        angular.forEach($scope.types, function(v){
          if(v.value == msg.info.customer_type){
            $scope.customerType = v;
          }
        });
        var init_list = ['address','remark','is_uploaded','is_located'];
        var i,len = init_list.length;
        for(i=0; i<len; i++) {
          $scope[init_list[i]] = msg.info[init_list[i]];
        }
        //保存目前的地址，防止丢失
        former_address = $scope.address;
      },function(err) {
        dialog.alert(err);
        $state.go('page.manage');
      });
    })(cus_id);
    //提交修改
    $scope.func.create = function() {
      //判断是否进行了修改或上传了照片
      if($scope.basic_form.$pristine && $scope.urls===null && $scope.userGeo.lat===null) {
        dialog.alert('提交失败，您并未做任何修改');
        return false;
      }
      var postData = {id:cus_id};
      //检测是否有删除用户信息的行为
      if($scope.basic_form.address.$dirty && $scope.address=="") {
        dialog.alert('地址信息不能为空');
        $scope.address = former_address;
        return false;
      }

      //需要检测的项目
      var modify_list = ['dimensions','address','direction','remark'];
      var not_null = ['dimensions','direction'];
      var i,len = modify_list.length;
      //检测是否存在必填项为空或者把必填项改成空值的情况
      for(i=0; i<not_null.length; i++) {
        if(!$scope[not_null[i]]) {
          dialog.alert('必填项不能为空');
          return false;
        }
      }

      //只提交修改的部分
      for(i=0; i<len; i++) {
        if($scope.basic_form[modify_list[i]].$dirty) {
          postData[modify_list[i]] = $scope[modify_list[i]];
        }
      }
      var dimensions = $scope.dimensions || {value: ''},
        direction = $scope.direction || {value: ''},
        customerType = $scope.customerType || {value: ''};
      postData['dimensions'] = dimensions.value;
      postData['direction'] = direction.value;
      postData['customerType'] = customerType.value;

      //如果上传了照片
      if($scope.urls !== null) {
        postData.pic_urls = $scope.urls;
      }
      //重新修改了定位信息
      if($scope.userGeo.lat !== null || $scope.userGeo.lng!==null) {
        postData.is_located = 1;
        postData.geo = {
          lat : $scope.userGeo.lat,
          lng : $scope.userGeo.lng
        };
        postData.address = $scope.address;
      }
      //异步请求
      $scope.isRequesting = true;
      rpc.load('customer/edit','POST',postData).then(function(msg) {
        dialog.alert('修改成功');
        $scope.isRequesting = false;
        $state.go('page.manage');
      },function(err) {
        dialog.alert('修改失败：'+err);
        $scope.isRequesting = false;
      });
    };
    //点击choosePlace之后的回调函数
    $window.geoData.callback = function(data) {
      $scope.userGeo.lat = data.latitude;
      $scope.userGeo.lng = data.longitude;
      $scope.address = data.address;
      $scope.$apply();
    };
    //上传照片的回调
    $window.callback_function.upload = function(urls) {
      $scope.urls = JSON.parse(urls);
      $scope.is_uploaded = 2;
      $scope.$apply();
    };

    //修改地址调用安卓接口进行定位
    $scope.func.choosePlace = function() {
      if(!$window.jsInterface || !$window.jsInterface.findRestaurantOnMap) {
        dialog.alert('请使用安卓设备');
      } else {
        $window.jsInterface.findRestaurantOnMap();
      }
    };

    //上传照片
    $scope.func.upload = function() {
      if(!$window.jsInterface || !$window.jsInterface.pictureUpload) {
        dialog.alert('无法上传图片,请安装安卓客户端','customer_shop');
      } else {
        $window.jsInterface.pictureUpload('window.callback_function.upload');
      }
    };
  }]);
