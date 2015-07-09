'use strict';

angular.module('dachuwang')
.controller('CtrendsOrderController', ['$scope','$state','rpc', function($scope,$state,rpc) {
  $scope.func = {
    userback : function() {
      $state.go('page.customerdetail.trends');
    }
  }
  $scope.isLoading = true;
  $scope.customerlist = {};
  rpc.load('cdetail/index','POST',{action:'get_order_detail',order_number:$state.params.order_number})
  .then(function(data){
    $scope.isLoading = false;
    $scope.data = data.list;
    $scope.customerlist.sale_name = $scope.data.sale_name.name;
    $scope.customerlist.number = $scope.data.order_number;
    $scope.customerlist.status = $scope.data.status;
    $scope.customerlist.money = parseInt($scope.data.total_price)/100;
    $scope.customerlist.reallymoney = parseInt($scope.data.deal_price)/100;
    for(var i in $scope.data.content){
      $scope.data.content[i].sum_price = parseInt($scope.data.content[i].sum_price)/100;
    }
    $scope.customerlist.time = $scope.data.created_time * 1000;
    $scope.data.deliver_time = $scope.data.deliver_time * 1000;
    $scope.customerlist.content = $scope.data.content;
  });
}]);
