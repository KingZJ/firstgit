'use strict';

angular.module('dachuwang')
  .controller('CtrendsController',['$scope', '$state', '$window', 'rpc', 'pagination', 'daChuDialog', function($scope, $state, $window, rpc, pagination, dialog) {
  $scope.startTime = null;
  $scope.endTime = null;
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
  function getTimeStamp(time) {
    return (new Date(time)).valueOf()/1000;
  }
  $scope.func = {
    orderinfo : function(item) {
      $state.go('page.customerdetail.orderdetail',{order_number:item.order_number});
    },
    sift : function() {
      var startTime = null,
          endTime = null;
      if($scope.startTime) {
        startTime = getTimeStamp($scope.startTime);
      }
      if($scope.endTime) {
        endTime = getTimeStamp($scope.endTime);
      }
      if(startTime === null && endTime === null) {
        dialog.alert('起始时间和结束时间至少选一项');
        return;
      }
      if(startTime !==null && endTime!==null && startTime>endTime) {
        dialog.alert('最早时间不能大于最晚时间');
        return;
      }
      $scope.orderlist = [];
      $scope.pagination.init(getLists);
      $scope.pagination.nextPage();
    },
    clear : function() {
      $scope.startTime = null;
      $scope.endTime = null;
      $scope.orderlist = [];
      $scope.pagination.init(getLists);
      $scope.pagination.nextPage();
    }
  }
  $scope.orderlist = [];
  function getLists(callback) {
    $scope.isloading = true;
    var itemsPerPage = 10,i,
        postData = {
          action : 'get_customer_orders',
          uid : parseInt($state.params.uid),
          itemsPerPage : itemsPerPage,
          currentPage : pagination.page
        };
    if($scope.startTime!==null) {
      postData.begin_time = getTimeStamp($scope.startTime);
    }
    if($scope.endTime!==null) {
      postData.end_time = getTimeStamp($scope.endTime);
    }
    rpc.load('cdetail/index','POST',postData)
    .then(function(data){
      $scope.isloading = false;
      for(i=0; i<data.list.length; i++) {
        data.list[i].created_time*=1000;
        $scope.orderlist.push(data.list[i]);
      }
      if(data.list.length < itemsPerPage) {
        callback(true);
      } else {
        callback(false);
      }
    });
  }
  $scope.pagination = pagination;
  $scope.pagination.init(getLists);
  $scope.pagination.nextPage();
}]);
