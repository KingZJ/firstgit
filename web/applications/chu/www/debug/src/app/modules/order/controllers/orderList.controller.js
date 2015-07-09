'use strict';
angular
.module('dachuwang')
.controller('orderListController',["$rootScope","$scope", "req", "$cookieStore", "$modal", "$window", 'pagination', 'userAuthService', '$stateParams', 'daChuConfig', function($rootScope,$scope, req, $cookieStore, $modal, $window, pagination, userAuthService, $stateParams, daChuConfig) {
  // 查看是否登陆
  userAuthService.checkLogin();

  $scope.dialog = function(config, modal_config) {
    var _config = {
      headerText:"提示信息",
      bodyText: "设置成功",
      closeText: '关闭'
    };
    var _modal_config = {
      templateUrl: 'myModalContent.html'
    };
    angular.extend(_modal_config, modal_config);
    angular.extend(_config, config);
    var modalInstance = $modal.open({
      templateUrl: _modal_config.templateUrl,
      controller: 'ModalInstanceCtrl',
      resolve: {
        items: function () {
          return _config;
        }
      }
    });
    modalInstance.result.then(function (selectedItem) {
      //$scope.selected = selectedItem;
    }, function () {
      //$log.info('Modal dismissed at: ' + new Date());
    });
  }

  $scope.orderlist = [];
  $scope.showType = $stateParams.status || 2;
  $scope.tabs = [
    {status: 2,   name: '待确认'},
    {status: 100, name: '待收货'},
    {status: 1,   name: '已完成'},
    {status: 0,   name: '已关闭'}
  ];
  $scope.isProcessing = true;

  var weixin_pay_url = '';

  var callBack = function(data) {
    if(data.status === 0) {
      $scope.orderlist = data.orderlist;
      $scope.isProcessing = false;
      $scope.user_type = data.type;
      $scope.total = data.total;
      weixin_pay_url = data.pay_url;
    }
    $scope.pagination = pagination;
    // 初始化分页回调
    pagination.init(function(callback) {
      req.getdata('order/lists', 'POST', function(data) {
        angular.forEach(data.orderlist, function(product){
          $scope.orderlist.push(product);
        });
        if(callback) {
          callback(!data.orderlist.length);
        }
      }, {
        page: pagination.page,
        status : $scope.showType
      });
    });
  };
  var reload_list = function(){
     req.getdata('order/lists', 'POST', callBack, {status: $scope.showType});
  }

  reload_list();
  $scope.setStatus = function(status) {
    $stateParams = status;
    $scope.showType = status;
    $scope.orderlist = [];
    $scope.isProcessing = true;
    req.getdata('order/lists', 'POST', callBack, {status: $scope.showType});
    // req.redirect('order/list/'+status);
  }

  $scope.setStatus($scope.showType);

  var delCallback = function(data) {
    if(data.status === 0) {
      $scope.dialog({
        bodyText:"取消订单成功",
        close:function(){
          reload_list();
        }
      });
    } else {
      $scope.dialog({
        bodyText:"取消失败"
      });
    }
  };


  // 买家取消订单
  $scope.cancel = function(orderid , minus) {
    if(minus != 0){
     $scope.dialog({
      bodyText: "本单已减免"+minus+"元,取消订单将视为自动放弃优惠,确认取消订单吗？",
      action: "confirm",
     ok: function() {
        $scope.orderCancel(orderid);
      },
      actionText:'确认',
      closeText:'取消'
    });
    return ;
    }
    $scope.dialog({
      bodyText: "确认取消订单吗？",
      action: "confirm",
      ok: function() {
        $scope.orderCancel(orderid);
      },
      actionText:'确认',
      closeText:'取消'
    });
  };

  $scope.orderCancel = function(orderid) {
    req.getdata('order/cancel','POST', delCallback, {order_id: orderid});
  };

  //卖家继续微信支付订单
  $scope.pay = function(order_number) {
    $rootScope.showLoading = true;
    $window.location.href = weixin_pay_url+'?order_number='+order_number;
  }
}])
