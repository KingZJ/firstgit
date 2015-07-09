'use strict';

angular
  .module('hop')
  .controller('ComplaintDetailCtrl', ['dialog', '$location', 'req', '$scope', '$modal', '$window','$cookieStore', '$stateParams', '$state', function(dialog, $location, req, $scope, $modal, $window, $cookieStore, $stateParams, $state) {
  $scope.data = '';
  $scope.deal_price = {};
  $scope.order_number = $stateParams.order_number;
  var getInfo = function() {
    req.getdata('complaint/order_info', 'POST', function(data){
      if(data.status == 0) {
        $scope.data = data.info;
        $scope.deal_price.key = data.info.total_price;
      }
    },{order_number: $scope.order_number});
  };
  getInfo();

  $scope.back = function() {
    history.go(-1);
  };

  $scope.create = function(order_number) {
    $state.go('home.complaintCreate', {order_number: order_number});
  };
  $scope.dialog = dialog.tips;
}]);
