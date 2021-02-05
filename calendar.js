var days_31 = ["01", "03", "05", "07", "08", "10", "12"];
var days_30 = ["04", "06", "09", "11"];
var months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
var display_month = 0;
var display_year = 0;

function leap_year(yr) {
    return ((yr % 4 == 0) && (yr % 100 != 0)) || (yr % 400 == 0);
}

function get_pretty_date(date) {
    return (months[date.getUTCMonth()] + " " + date.getUTCDate() + ", " + date.getUTCFullYear());
}

function get_day_activities(date_string) {
    var result;
    $.get('proc.php', {
            f: 'get_miles_between_dates',
            start: date_string + " 00:00:00Z",
            end: date_string + " 23:59:59Z",
            type: "*"
        })
        .done(function(msg) {
            for (result of msg) {
                $(".card-text").append("<h3><a href='activity.html?" + result[4] + "'>" + result[2].charAt(0).toUpperCase() + result[2].slice(1) + "</a></h3>" + result[0] + " miles in " + seconds_to_formatted_string(parseInt(result[3])) + "<hr>");
            }
        });
}


function generate_calendar(sel_month, sel_year) {
    var limit = 0;
    display_month = parseInt(sel_month);
    display_year = parseInt(sel_year);

    if (days_31.includes(sel_month)) {
        limit = 31;
    } else if (days_30.includes(sel_month)) {
        limit = 30;
    } else {
        limit = leap_year(parseInt(sel_year)) ? 29 : 28;
    }

    var first_day = new Date(sel_year + "-" + sel_month.padStart(2, "0") + "-01T00:00:00Z");
    var index = first_day.getDay();
    var day_count = 1;
    var weeks = 5;

    index > 0 ? weeks = 5 : weeks = 6;

    var result = `<h2 id='month_year' class='m-2 mt-4 text-center'><button type="button" id="prev_month" class="btn btn-light">&lt;</button> ${months[parseInt(sel_month) - 1]}&nbsp;${sel_year} <button type="button" class="btn btn-light" id="next_month">&gt;</button></h2>`;
    result += "<table class='shadow-sm table table-striped'>\n<thead>\n<tr class='text-center border'>\n<th class='p-3'>Total</th><th class='p-3'>Sun</th><th class='p-3'>Mon</th><th class='p-3'>Tue</th><th class='p-3'>Wed</th><th class='p-3'>Thu</th><th class='p-3'>Fri</th><th class='p-3'>Sat</th>\n</thead>\n</tr>\n<tr>";


    for (i = 0; i < 6; i++) {

        result += "<tr class='border'><th class='text-center border' id='total-" + i + "'>0.0</th>";

        var j = 0;
        if (i == 0) {
            j = index + 1;
            for (k = 0; k <= index; k++) {
                result += "<td class='p-3'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>";
            }
        }
        for (j; j < 7; j++) {
            if (day_count <= limit) {
                result += "<td class='p-3 border' id='day-" + day_count + "'><h6><a href='day.html?" + display_year + "-" +
                    (display_month < 10 ? "0" + display_month.toString() : display_month.toString()) +
                    "-" + (day_count < 10 ? "0" + (day_count++).toString() : (day_count++).toString()) + "'>" + (day_count - 1) + "</a></h6><span class='contents text-muted'>&nbsp;</span></td>";
                index++;
            }

        }
        result += "</tr>";
    }
    result += "</table>";

    return result;
}

function populate_calendar(activity_types) {
    var start = display_year.toString() + "-" + display_month.toString().padStart(2, "0") + "-01";
    if (days_31.includes(display_month.toString().padStart(2, "0"))) {
        var end = display_year.toString() + "-" + display_month.toString().padStart(2, "0") + "-31 23:59:59";
    } else if (days_30.includes(display_month.toString().padStart(2, "0"))) {
        var end = display_year.toString() + "-" + display_month.toString().padStart(2, "0") + "-30 23:59:59";
    } else if (leap_year(display_year)) {
        var end = display_year.toString() + "-" + display_month.toString().padStart(2, "0") + "-29 23:59:59";
    } else {
        var end = display_year.toString() + "-" + display_month.toString().padStart(2, "0") + "-28 23:59:59";
    }

    var daily_totals = Array(32).fill(0.0);
    var weekly_total = 0.0;

    $.get('proc.php', {
            f: 'get_miles_between_dates',
            start: start,
            end: end,
            type: activity_types
        })
        .done(function(msg) {
            for (result of Object.keys(msg)) {
                event_day = parseInt(msg[result][1].split(" ")[0].split("-")[2]);

                daily_totals[event_day] = parseFloat(msg[result][0]) + parseFloat(daily_totals[event_day]);
            }
            $(".contents").html("&nbsp;");
            weekly_count = 0;
            for (i = 0; i < 32; i++) {

                if (daily_totals[i] > 0) {
                    $("#day-" + i + " .contents").html(parseFloat(daily_totals[i]).toFixed(1) + " mi");
                }
            }
            for (i = 1; i < 6; i++) {
                weekly_total = 0.0;
                for (child of $("#calendar table tbody").children()[i]['children']) {
                    if ($(child).children()["length"] > 1) {
                        if (!isNaN(parseFloat($($(child).children()[1]).text()))) {
                            weekly_total += parseFloat($($(child).children()[1]).text());
                        }

                    }
                }
                $("#total-" + (i - 1)).html(Number.parseFloat(weekly_total).toFixed(1));


            }
        });
}

$(document).on('click', '#prev_month', function() {

    if (display_month == 1) {
        display_month = 12;
        display_year--;
    } else {
        display_month--;
    }

    $("#calendar").html(generate_calendar((display_month).toString().padStart(2, "0"), display_year.toString()));
    populate_calendar($("#activity_filter").val());
});

$(document).on('click', '#next_month', function() {

    if (display_month == 12) {
        display_month = 1;
        display_year++;
    } else {
        display_month++;
    }

    $("#calendar").html(generate_calendar((display_month).toString().padStart(2, "0"), display_year.toString()));
    populate_calendar($("#activity_filter").val());
});