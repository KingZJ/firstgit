'use strict';

angular.
  module('dachuwang').
  controller('MoreController', ['$scope', '$window', 'daChuDialog', function($scope, $window, dialog) {
    $scope.func = {};
    $scope.func.manual = function() {
      dialog.alert('正在开发中...');
    }
    $scope.func.map = function() {
      if(!$window.jsInterface || !$window.jsInterface.offlineMapManager) {
        dialog.alert('请安装安卓客户端使用此功能');
      } else {
        $window.jsInterface.offlineMapManager();
      }
    }
    $scope.func.clearCache = function() {
      if(!$window.jsInterface || !$window.jsInterface.eraseCache) {
        dialog.alert('请安装安卓客户端使用此功能');
      } else {
        $window.jsInterface.eraseCache();
      }
    }
}]);
