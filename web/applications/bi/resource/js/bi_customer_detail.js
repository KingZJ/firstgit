// 路径配置
require.config({
  paths: {
    echarts: 'http://echarts.baidu.com/build/dist'
  }
});
// 使用
require([
  'echarts',
  'echarts/chart/line', // 使用柱状图就加载bar模块，按需加载
  'echarts/chart/bar'
],
DrawEChart            //异步加载的回调函数绘制图表
       );

       var myChart;
       function DrawEChart (ec) {
         //定义图标options
         var options = {
           title : {
             text : "客户下单情况表",
           },
           tooltip : {
             trigger: 'axis'
           },
           calculable : true,
           legend: {
             data:['下单金额','所有客户平均下单金额']
           },
           xAxis : [
             {
             type : 'category',
             data : []
           }
           ],
           yAxis : [
             {
             type : 'value',
             name : '金额(￥)',
             axisLabel : {
               formatter: '{value}'
             }
           }
           ],
           series : [
             {
             name:'下单金额',
             itemStyle: {normal: {color:'rgba(255,153,0,0.9)', label:{show:true,formatter:function(p){return p.value }}}},
             type:'bar',
             data:[]
           },
           {
             name:'所有客户平均下单金额',
             itemStyle: {normal: {color:'rgba(135,206,250,0.9)'}},
             type:'line',
             data:[]
           }
           ]
         };

         //通过Ajax获取默认最近30天数据
         Ajax_request(ec, options);

         //点击重置按钮
         $('#customer_reset').on('click',function(){
           //通过Ajax获取数据
           Ajax_request(ec, options);
         });

         //改变month.触发ajax
         $('#datepicker_customer').datepicker().on('changeMonth', function(e){
           var changeDate = new Date();
           changeDate = e.date;
           var new_month = get_current_month(changeDate);
           //通过Ajax获取数据
           Ajax_request(ec, options, new_month);
         });


       }//DrawEChart

       //初始化日期
       $('#datepicker_customer').datepicker({
         minView : 'year',
         language: 'zh-CN',
         format: 'yyyy-mm',
         startView:'year',
         minViewMode:"months",
         autoclose:true
       });
       //默认显示当月
       $('#datepicker_customer input').attr('value', '请选择年月');

       //获取月份
       function get_current_month(myDate) {
         var Year   = myDate.getFullYear();
         var month  = myDate.getMonth() + 1;
         var Month  = (month < 9) ? '0'+ month : month;
         return Year + '-' +Month;
       }

       //ajax  请求
       function Ajax_request(ec, options, month) {
         myChart = ec.init(document.getElementById('customer')); 
         myChart.showLoading({
           text: "图表数据正在努力加载..."
         });

         if(month == undefined){
           month = "";
         }else{
           month= "&time="+month;
         }

         //获取url search部分
         var search_url = location.search;
         //通过Ajax获取默认最近30天数据
         $.ajax({
           type: "GET",
           async: false, //同步执行
           url: "get_cus_period_amount"+search_url+month,
           dataType: "json", //返回数据为json
           success: function (result) {
             if(result) {
               options.xAxis[0].data  = result.res.date;
               options.series[0].data = result.res.amount;
               options.series[1].data = result.res.average;
             }
           },
           error: function (errorMsg) {
             alert("图表请求数据失败啦!刷新再来一次吧");
           }
         });
         myChart.hideLoading();
         myChart.setOption(options);


         //点击事件监听
         var ecConfig = require('echarts/config');
         function eConsole(param) {
           if(param.type == 'click') {

             //获取url search部分
             var search_url = location.search;
             var date = '&time='+param.name;
             //通过Ajax获取某天下单详情
             $.ajax({
               type: "GET",
               async: false, //同步执行
               url: "get_one_cus_order_detail"+search_url+date,
               dataType: "json", //返回数据为json
               success: function (result) {
                 if(result) {
                   $('#customer_detail_modal_tital').html(param.name+'客户下单详情');
                   $('#customer_detail_modal tbody').html("<tr><th>订单号</th><th>商品名称</th><th>单价</th><th>数量</th><th>小计(￥)</th></tr>");
                   for(var items  in result.res.order_details){
                     var orders = result.res.order_details[items];
                     var orders_length = orders.length;
                     for(var i=0; i < orders_length ;i++ ) {
                       var mes = '<tr><td>'+orders[i].order_number+'</td><td>'+orders[i].name+'</td><td>'+orders[i].price+'</td><td>'+orders[i].quantity+'</td><td>'+orders[i].sum_price+'</td></tr>';
                       $('#customer_detail_modal tbody').append(mes);
                     }
                   }
                   var total ='<tr><td colspan="3">总计</td><td>'+result.res.total.total_quantity+'</td><td>'+result.res.total.total_sum_price+'</td></tr>';
                   $('#customer_detail_modal tbody').append(total);
                 }
               },
               error: function (errorMsg) {
                 alert("数据请求失败，再来一次！");
               }
             });

             //模态框
             $('#myModal').modal(options);
           }
         };

         myChart.on(ecConfig.EVENT.CLICK, eConsole);
         myChart.on(ecConfig.EVENT.DBLCLICK, eConsole);
       }



      //不同规模对应不同的颜色
      var dimension = $('#customer_dimension').html();
      switch (dimension) {
        case '20-50平' :
          $('#customer_dimension').addClass('label-info');
          break;
        case '10平以下' :
          $('#customer_dimension').addClass('label-primary');
          break;
        case '10-20平' :
          $('#customer_dimension').addClass('label-success');
          break;
        case '50-100平' :
          $('#customer_dimension').addClass('label-warning');
          break;
        case '100平以上' :
          $('#customer_dimension').addClass('label-danger');
          break;

      }
