'use strict';
angular
.module('hop')
.controller('CouponAddCtrl', ['$scope', '$stateParams', 'req', '$upload', 'daChuLocal', 'dialog' ,function($scope, $stateParams, req, $upload, daChuLocal , dialog) {
  // 增加广告
  $scope.title = '发券活动创建';
  //-----
  // 日期选择控件初始化
  $scope.dateOptions = {
    formatYear: 'yy',
    startingDay: 1
  };
  $scope.endDateOptions = {
    formatYear: 'yy',
    startingDay: 1
  };
  $scope.endOpened = $scope.opened = false;
  $scope.open = function($event) {
    $event.preventDefault();
    $event.stopPropagation();
    $scope.opened = true;
  };
  $scope.endOpen = function($event) {
    $event.preventDefault();
    $event.stopPropagation();
    $scope.endOpened = true;
  };

  // 默认的规格选项值输入框
  $scope.initValues =   {
    name: '添加',
    value: '',
    id: '',
    icon: 'glyphicon-plus',
    cls: 'btn-info',
    clk: 'addProduct'
  };
  // 规格值输入框数组初始化
  $scope.products = [$scope.initValues];

  // 添加
  $scope.addProduct = function($index, v) {
    if(v == undefined) {
      v = '';
    }
    var next = {
      name: '删除',
      value: v,
      id: '',
      icon: 'glyphicon-minus',
      cls: 'btn-danger',
      clk: 'remove'
    };
    $scope.products.push(next);
  };

  // 删除
  $scope.remove = function(item) {
    var index = $scope.products.indexOf(item);
    $scope.products.splice(index, 1);
  };
  //-------
  $scope.showProduct = function(item) {
    var index = $scope.products.indexOf(item);
    var postData = {
      locationId : $scope.location.id,
      searchVal : item.value
    };
    req.getdata('product/manage', 'POST', function(data) {
      item.products = data.list;
    }, postData);
  }
  $scope.selectProduct = function(product, item) {
    var index = $scope.products.indexOf(item);
    $scope.products[index].id = product.id;
    $scope.products[index].value = product.title + '|' + product.sku_number + '|' + product.price +'/' + product.unit;
    item.products = '';
  }
  //--------
  var setDefault = function() {
    req.getdata('coupon/input_options', 'POST', function(data) {
      if(parseInt(data.status) === 0) {
        $scope.ruleInfo = data.list.ruleInfo;
        $scope.name = data.list.ruleInfo;
        // 发放优惠券次数
        $scope.couponNums = 1;
        $scope.locationInfo = data.list.location;
        $scope.siteSrcs = data.list.sites;
        $scope.lineOptions = data.list.line_options;
        $scope.visiables = data.list.visiable_options;
        $scope.visiable = data.list.visiable_options[0];
        $scope.couponObjects = data.list.couponObjects;
        $scope.couponTriggers = data.list.couponTriggers;
        $scope.couponTrigger = data.list.couponTriggers[0];
        $scope.site = $scope.siteSrcs[0];
        $scope.location = $scope.locationInfo[0];
        $scope.lines = $scope.lineOptions[$scope.location.id];
      }
    }, {id: $stateParams.ruleId});
  }
  $scope.link_url = '';
  setDefault();
 // 保存
  $scope.add = function(addForm) {
    if(addForm.$invalid ){
       dialog.tips({
         bodyText : '请填写完整信息！'
       })
       return ;
    }
    var postData = {
      title : $scope.name,
      ruleId : $stateParams.ruleId,
      couponNums : $scope.couponNums,
      siteId : $scope.site.id,
      locationId : $scope.location.id,
      products : [],
      categories : [],
      validTime : '',
      invalidTime : '',
      visiable : $scope.visiable.id,
      couponTriggerId : $scope.couponTrigger.id
    };
    postData.validTime = Date.parse($scope.startTime)/1000;
    postData.invalidTime = Date.parse($scope.endTime)/1000;
    postData.couponTriggerId = $scope.couponTrigger.id;
    angular.forEach($scope.products, function(v) {
      if(parseInt(v.id) > 0) {
        postData.products.push(v.id);
      }
    });
    req.getdata('coupon/create', 'POST', function(data) {
      alert(data.msg);
      req.redirect('/coupon');
    }, postData);
  }
  // 切换城市
  $scope.selectCity = function() {
    $scope.lines = $scope.allLines[$scope.init.location.id];
  }

  $scope.setStatus = function(item, status) {
    req.getdata('coupon/set_status', 'POST', function(data) {
      var index = $scope.coupons.indexOf(item);
      $scope.coupons[index].status = status;
      alert(data.msg);
    }, {id: item.id, status: status});
  }

}]);
