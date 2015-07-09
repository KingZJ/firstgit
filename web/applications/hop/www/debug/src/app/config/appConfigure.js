'use strict';

//配置url地址
angular
  .module('hop')
  .provider('appConfigure', {
    $get: function($location) {
      var domain = $location.$$host;
      if(domain.indexOf('dachuwang') < 0) {
        domain = 'hop.dachuwang.net';
      }
      return {
        url: 'http://api.' + domain
      };
    }
  });
