/**
 * BI系统JS控制程序
 * @author zhangxiao@dachuwang.com
 */

$(document).ready(function(){

    //监听鼠标滑动popover事件
    $(".pop").on('mouseenter',function(){
        $(this).popover({
            html: true
        }).popover('show');
    });

    $(".pop").on('mouseleave',function(){
        $(this).popover('hide');
    });

    //文档load完毕监听表头并固定
    setTimeout(function() {
        fixTable();
    }, 500);

    //当用户resize窗口，重新初始化监听
    $(window).on("resize", function(){
        fixTable();
    });

    //重置button监听
    $('.reset').on('click', function(){
        $('.search-value').attr("value", "");
    });

    //初始化日期
    $('#datepicker').datepicker({
        language: 'zh-CN',
        format: 'yyyy-mm-dd',
        todayBtn: 'linked',
        todayHighlight: true
    });
    
    $('.J-datepicker-statics').datepicker({
        language: 'zh-CN',
        format: "yyyy-mm",
        startView: "months", 
        minViewMode: "months",
        defaultViewDate: "year",
        autoclose: true,
        orientation : "auto top"
    });

    //监听日期变化
    $('#datepicker').datepicker().on('changeDate', function(e){
        $('#past-7-days').removeClass('active');
        $('#today').removeClass('active');
        $('#yesterday').removeClass('active');
        $("[name='is_tab_id']").attr('value','false');
        if($(e.target).attr('name') == 'from'){
            $("[name='sdate']").attr('value', e.format([0],"yyyy-mm-dd"));
        }else if($(e.target).attr('name') == 'to'){
            $("[name='edate']").attr('value', e.format([0],"yyyy-mm-dd"));
        }
    });
    $("[name='sdate']").attr('value', $('.from').attr('value'));
    $("[name='edate']").attr('value', $('.to').attr('value'));

    //监听选择项
    $('#search-Value').attr('placeholder', '请输入'+$(".search-key > option[selected]").text());
    $(".search-key").on('change', function(e) {
        $(".search-key > option[selected]").attr('selected', false);
        if (this.value == 'c_name') {
            $('#search-Value').attr('placeholder', '请输入客户姓名');
        } else if (this.value == 'c_tel') {
            $('#search-Value').attr('placeholder', '请输入客户电话');
        } else if (this.value == 'c_shop') {
            $('#search-Value').attr('placeholder', '请输入客户店铺名称');
        } else if (this.value == 'c_id') {
            $('#search-Value').attr('placeholder', '请输入客户ID');
        } else {
            $('#search-Value').attr('placeholder', '请输入客户姓名');
        }
    });

    //每个客户订单详情页面，table样式
    $('#customer_detail tbody tr td:even').addClass('table_font_style_item');
    $('#customer_detail tbody tr td:odd').addClass('table_font_style_value');

    //监听表头到top并固定表头
    var fixTable = function(){
        if($('.table-show').length <= 0) {
            return false;
        }
        //测量表头宽度并复制给隐藏表头
        $(".table-show thead>tr>th").each(function(index,element){
            var width = $(element).outerWidth();
            $(".table-hide thead>tr>th").eq(index).attr("width",width);
        });
        var theadHeight = $('.nav-table').offset().top;
        $(window).scroll(function(){
            var scroHeight = $(this).scrollTop();
            if((scroHeight+50) >= theadHeight && $(window).width() > 916){
                $(".table-hide").css({"display":"table","position":"fixed","top":40,"z-index":1000});
            }else{
                $(".table-hide").css({"display":"none"});
            }
        });
    }
});


