var rangeSlider = function(){
  var slider = $('.range-slider'),
      range = $('.range-slider__range'),
      value = $('.range-slider__value');
    
  slider.each(function(){

    value.each(function(){
      var value = $(this).prev().attr('value');
      $(this).html(parseInt(value));
    });

    range.on('input', function(){
      $(this).next(value).html(this.value);
    });
  });
};

var refreshRange = function () {
    $(".range-slider__range").each(function () {
        $(this).attr('value', '0');
    })
};

$( rangeSlider );