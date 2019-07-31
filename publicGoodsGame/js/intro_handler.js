$( function () {
    
    var ino = 0;
    var leave = false;
    var nimages = $(".intro-img").length;

    $(document).keydown(function (e) {
        if (!leave) {
            switch(e.which) {
                case 13: // enter
                case 40: // down
                case 39: // right
                    if (ino < nimages) ino++;
                    if (ino >= nimages) leave = true;
                break;

                case 38: // up
                case 37: // left
                    if (ino > 0) ino--;
                break;

                case 27: // escape
                    leave = true;
                break;

                default: return; // exit this handler for other keys
            }
            if (leave) {
                $("#intro").hide();
            } else {
                $(".intro-img:not(:eq("+ino+"))").hide();
                $(".intro-img:eq("+ino+")").show();
            }
            e.preventDefault(); // prevent the default action (scroll / move caret)
        }
    });
    
    $("#intro-next").click(function () {
        if (!leave) {            
            if (ino < nimages) ino++;
            if (ino >= nimages) leave = true;
            
            if (leave) {
                $("#intro").hide();
            } else {
                $(".intro-img:not(:eq("+ino+"))").hide();
                $(".intro-img:eq("+ino+")").show();
            }
        }
    });
    $("#intro-prev").click(function () {
        if (!leave && ino > 0) {
            ino--;
        
            $(".intro-img:not(:eq("+ino+"))").hide();
            $(".intro-img:eq("+ino+")").show();
        }
    });
    $("#intro-close").click(function () {
        leave = true;
        $("#intro").hide();
    });
});