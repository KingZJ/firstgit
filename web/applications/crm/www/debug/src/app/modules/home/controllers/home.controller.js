'use strict';

angular
.module('dachuwang')
.controller('homeCtrl', ['$scope', '$window', '$log', '$stateParams', '$filter','daChuLocal', 'rpc','userAuthService','daChuDialog', function($scope, $window, $log, $stateParams, $filter, daChuLocal, rpc, userAuthService, dialog) {
  userAuthService.checkLogin();
  $scope.tabLists = [{status:0,name:'统计数据'},{status:1,name:'本月绩效'}];
  $scope.showType = 0;
  $scope.showTabs = true;
  //显示可选区间模块的table
  $scope.show_metabolic = false;
  $scope.dpicker = {
    begin : {},
    end : {}
  };
  $scope.format = {
    min : new Date('2015-03-01'),
    max : new Date()
  };
  var undifine = '未知';
  $scope.statistics = [
    {
      heading : '今日',
      data : [{key:'新注册客户数',val:undifine},{key:'首单客户数',val:undifine}]
    },
    {
      heading : '本周',
      data : [{key:'新注册客户数',val:undifine},{key:'首单客户数',val:undifine}],
    },
    {
      heading : '本月',
      data : [{key:'新注册客户数',val:undifine},{key:'首单客户数',val:undifine}]
    }
  ];
  //可展开面板每一项默认的展开和关闭状态 true表示展开
  $scope.openstatus = {
    list : [true,true,true,true,true,true],
    query : false,
    sum : true
  };
  $scope.capacity_openstatus = {
    list : [true],
    query : false,
    sum : true
  };
  $scope.metabolic = [{key:'新注册客户数',val:undifine},{key:'首单客户数',val:undifine}];
  $scope.sum = [{key:'首单客户数',val:500}];
  $scope.begintime = {};
  $scope.endtime = {};
  $scope.date = {};
  $scope.func = {
    query : function() {
      if(!$scope.date.begin_time || !$scope.date.end_time) {
        dialog.alert('请选择时段');
        return false;
      }
      var bt = new Date($scope.date.begin_time).valueOf();
      var et = new Date($scope.date.end_time).valueOf();
      //起始时间是否小于等于结束时间
      if(bt>et) {
        dialog.alert('起始时间不能大于结束时间');
        return false;
      }
      $scope.isquering = true;
      $scope.show_metabolic = false;
      var postData = {
        role_id : parseInt(daChuLocal.get('role_id')),
        action : 'get_query',
        begin_time : bt/1000,
        end_time : et/1000
      };
      if($stateParams.bd_id) {
        postData.bd_id = $stateParams.bd_id;
      }
      rpc.load('statistics/index','POST',postData)
        .then(function(msg) {
          $scope.metabolic = msg.list;
          $scope.show_metabolic = true;
        },function(err) {
          dialog.alert('查询失败请稍候再试，原因: '+err);
        })
        .then(function() {
          $scope.isquering = false;
        });
    },
    openDatepicker : function($event,which) {
      $event.preventDefault();
      $event.stopPropagation();
      if(which === 1) {
        $scope.dpicker.end.status = false;
        $scope.dpicker.begin.status = true;
      } else {
        $scope.dpicker.begin.status = false;
        $scope.dpicker.end.status = true;
      }
    },
    changeStatus : function(status) {
      $scope.showType = status;
    }
  };
  $scope.isloading = true;
  $scope.isquering = false;
  function getStatistics() {
    var postData = {
      role_id : parseInt(daChuLocal.get('role_id')),
      action : 'get_statistics'
    };
    if($stateParams.bd_id) {
      postData.bd_id = $stateParams.bd_id;
    }
    rpc.load('statistics/index','POST',postData)
      .then(function(msg) {
        if(msg.list.length == 5){
          $scope.statistics = msg.list.splice(0, msg.list.length - 1);
          $scope.capacity = msg.list.splice(msg.list.length-2, 1);
        }else{
          $scope.statistics = msg.list;
        }
        var len = $scope.statistics.length - 1;
        $scope.cuslist = []
        $scope.customerlist = $scope.statistics[len].data;
        angular.forEach($scope.customerlist, function(value,key){
          $scope.cuslist.push({name:key,value:value})
        })
      },function(err) {
        console.error(err);
      })
      .then(function() {
        $scope.isloading = false;
      });
  }
  getStatistics();
}])
