'use strict';

angular.module('dachuwang')
.controller('CdetailInfoController',['$rootScope','$scope', '$state', '$window', 'rpc', 'daChuDialog','Lightbox','urlHistoryService','daChuLocal', function($rootScope,$scope, $state, $window, rpc, dialog, Lightbox, urlHistoryService, daChuLocal) {
  $scope.customer = {};
  $scope.history = {};
  $scope.baseinfo = {};
  $scope.isLoading = true;
  $scope.ka_info = true;
  rpc.load('cdetail/index','POST',{action:'get_all',uid:$state.params.uid})
  .then(function(data){
    $scope.isLoading = false;
    $scope.imgurl = data.list.basic_info.urls;
    $scope.customer.sms = data.list.sms;
    $scope.history = data.list.his_data;
    $scope.baseinfo = data.list.basic_info;
  });
  //图片点击放大
  $scope.openmodal = function(index){
    var images = [];
    for(var i=0; i<$scope.imgurl.length; i++) {
      images.push({url:$scope.imgurl[i].url});
    }
    $rootScope.imglength = images.length; 
    Lightbox.openModal(images, index); 
  }
  //不是bd
  $scope.role_id = true;
  var role_id = parseInt(daChuLocal.get('role_id'));
  if(role_id != 12){
     $scope.role_id = false;
  }
 
  $scope.func = {
    edit_customer : function() {
      if($scope.baseinfo.ka_info) {
        dialog.alert('不允许编辑KA客户');
        return;
      }
      $state.go('page.editcustomer',{customer_id:$state.params.uid});
    },
    reset_password : function() {
      console.log('reset');
      $scope.resetClk = true;
      dialog.tips(
      {
        bodyText: '是否要重置该用户密码',
        actionText: "确定",
        ok: function() {
          var postData = {uid: $state.params.uid};
          rpc.load('customer/update_password', 'POST', postData).then(function(data) {
            $scope.resetClk = false;
            dialog.alert(data.content);
          }, function(msg) {
            dialog.alert('重置密码失败，请联系技术人员');
          });
        }
      }
      );
    },
    history_detail : function() {
      $state.go('page.customerdetail.cdetailhistory');
    },
    view_map : function() {
      var geoinfo = {
        lat : $scope.baseinfo.lat,
        lng : $scope.baseinfo.lng
      };
      if(!geoinfo.lat || !geoinfo.lng) {
        dialog.alert('位置信息不全无法查看,请更新位置信息');
      } else {
        if($window.jsInterface && $window.jsInterface.searchLine) {
          $window.jsInterface.searchLine(JSON.stringify({latitude:geoinfo.lat,longitude:geoinfo.lng}));
        } else {
          dialog.alert('lat:'+geoinfo.lat+', lng:'+geoinfo.lng+' 暂不支持查看地图');
        }
      }
    },
    new_register_change_shared : function() {
      dialog.tips(
        {
          bodyText: '是否确定要把客户放到公海?',
          actionText: "确定",
          ok: function() {
            $scope.change_button = true;
            var cid = parseInt($scope.baseinfo.id);
            rpc.load('shared_customer/new_register_change_shared', 'POST', {cid : cid})
            .then(function(res) {
              if(res.status == 0) {
                dialog.alert('操作成功');
                //跳转到私海新注册用户
                urlHistoryService.push(1);
                $state.go('page.manage');
              } else {
                dialog.alert('操作失败：'+res.msg);
              }
            })
            .then(function() {
              $scope.change_button = false;
            }, function() {
              dialog.alert('操作失败：网络不好或服务器内部错误');
              $scope.change_button = false;
            });
          }
        }
      );
    }
  }
}]);
