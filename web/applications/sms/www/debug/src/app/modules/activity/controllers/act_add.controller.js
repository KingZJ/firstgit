'use strict';
angular
.module('hop')
.controller('ActivityAddCtrl', ['$scope', 'req', 'rpc', '$upload', 'daChuLocal', function($scope, req, rpc, $upload, daChuLocal) {
  // 增加广告
  $scope.title = '发布运营活动';
  var setDefault = function() {
    req.getdata('promotion/input_options', 'GET', function(data) {
      if(parseInt(data.status) === 0) {
       $scope.siteSrcs = data.list.sites;
       $scope.site = data.list.sites[0];
       $scope.locationInfo = data.list.locations;
       $scope.location = data.list.locations[0];
       $scope.type = data.list.types;
       $scope.orderTypes = data.list.order_types;
       $scope.firstOptions = data.list.first_options;
       $scope.isFirst = $scope.firstOptions[1];
      }
    });
  }
  setDefault();
  // 选择分类
  $scope.getMaps = function() {
    // 获取分类映射
    // 站点id，地理位置id
  }
  // 日期选择控件初始化
  $scope.dateOptions = {
    formatYear: 'yy',
    startingDay: 1
  };
  $scope.endDateOptions = {
    formatYear: 'yy',
    startingDay: 1
  };
  $scope.latestOptions = {
    formatYear: 'yy',
    startingDay: 1
  };

  $scope.endOpened = $scope.latestOpened = $scope.opened = false;
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

  $scope.latestOpen = function($event) {
    if( $scope.endTime == undefined || $scope.startTime == undefined) {
      alert('请先选择活动时间范围');
      return false;
    }
    $event.preventDefault();
    $event.stopPropagation();
    $scope.latestOpened = true;
  };
  // 保存
  $scope.add = function() {
    var postData = {
      title : '',
      location_id : 1,
      rule : '',
      startTime : '',
      endTime : '',
      latestDeliverTime : '',
      site_id : '',
      categoryNames : '',
      categoryLimitNum: 0,
    };
    postData.site_id           = $scope.site.id;
    postData.location_id       = $scope.location.id;
    postData.rule              = $scope.rule;
    postData.title             = $scope.name;
    postData.startTime         = Date.parse($scope.startTime) / 1000;
    postData.endTime           = Date.parse($scope.endTime) / 1000;
    postData.latestDeliverTime = Date.parse($scope.latestDeliverTime) / 1000;
    postData.categoryNames     = $scope.categoryNames ? $scope.categoryNames : '';
    postData.categoryLimitNum  = $scope.categoryLimitNum ? $scope.categoryLimitNum : 0;
    postData.ruleType          = $scope.ruleType ? $scope.ruleType.id : 0;
    postData.orderType         = $scope.orderType ? $scope.orderType.id : 0;
    postData.isFirst           = $scope.isFirst ? $scope.isFirst.id : 1;

    if(!postData.title) {
      alert('请输入活动标题');
      return false;
    }
    if(!$scope.ruleType) {
      alert('请选择活动类型');
      return false;
    }
    if(!$scope.orderType) {
      alert('请选择参与活动的订单类型');
      return false;
    }
    rpc.load('promotion/save', 'POST', postData).then(
      function(data) {
        alert(data.msg);
        req.redirect('/activity');
      },
      function(msg) {
        alert(msg);
      }
    );
  }
}]);
