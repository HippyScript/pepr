var units = "miles";

function get_max_min_elevation(elevations) {
    var max = Math.max.apply(Math, elevations);
    var min = Math.min.apply(Math, elevations);

    return [max, min];
}

function get_sunday(start) {
    start = new Date(start);

    diff = start.getDate() - start.getDay();
    /*+ (start.getDay() == 0 ? -6 : 1)*/

    result = new Date(start.setDate(diff));
    return result.toISOString().split('T')[0];
}

function seconds_to_formatted_string(seconds) {
    // Hours, minutes and seconds
    var hrs = Math.floor(seconds / 3600);
    var mins = Math.floor((seconds % 3600) / 60);
    var secs = Math.floor(seconds % 60);

    var result = "";

    if (hrs > 0) {
        result += "" + hrs + ":" + (mins < 10 ? "0" : "");
    }

    result += "" + mins + ":" + (secs < 10 ? "0" : "");
    result += "" + secs;
    return result;
}

function get_total_miles(activity_type) {
    $.get('proc.php', {
            f: 'get_total_miles',
            type: activity_type
        })
        .done(function(msg) {
            $("#total_miles").html(parseFloat(msg).toFixed(1) + " miles");
        });
}

function get_year_miles(activity_type) {
    var today = new Date();
    today.setDate(today.getDate() + 1);
    var start = today.getFullYear() + "-01-01";
    $.get('proc.php', {
            f: 'get_miles_between_dates',
            start: start,
            end: today.toISOString().split('T')[0],
            type: activity_type
        })
        .done(function(msg) {
            var tally = 0.0;
            for (result of Object.keys(msg)) {
                tally += parseFloat(msg[result][0]);
            }
            $("#year_miles").html(tally.toFixed(1) + " miles");
        });
}


function get_week_miles(activity_type) {
    today = new Date();
    today.setDate(today.getDate());
    $.get('proc.php', {
            f: 'get_miles_between_dates',
            start: get_sunday(Date.now()),
            end: today.toISOString().split('T')[0],
            type: activity_type
        })
        .done(function(msg) {
            var tally = 0.0;
            for (result of Object.keys(msg)) {
                tally += parseFloat(msg[result][0]);
            }
            $("#week_miles").html(tally.toFixed(1) + " miles");
        });
}

function get_activity(activity_id) {
    $.get('proc.php', {
            f: 'get_activity',
            id: activity_id
        })
        .done(function(msg) {

            var activity_date = new Date(msg["activity_start"]);
            if (msg["activity_type"] == "run" ||
                msg["activity_type"] == "hike" ||
                msg["activity_type"] == "swim" ||
                msg["activity_type"] == "bike" ||
                msg["activity_type"] == "kayak" ||
                msg["activity_type"] == "sup" ||
                msg["activity_type"] == "elliptical") {
                points = msg[10]["points"];
                if (typeof(points[0]) != 'undefined') {
                    activity_date = new Date(points[0][1]["time"][0]);
                }

            }

            var opts = {
                weekday: 'short',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };

            $(".card-header").html("<img style='height: 45px; width: 45px;' alt='" + msg["activity_type"] + "' src='icons/" + msg["activity_type"] + ".svg'>&nbsp;" + msg["activity_name"] + "<span class='text-muted float-right'>" + activity_date.toLocaleDateString(undefined, opts) + "</span>");

            $("#duration").html(seconds_to_formatted_string(msg["activity_duration"]));
            $("#description").html(msg["activity_description"]);
            if (msg["activity_type"] == "yoga" ||
                msg["activity_type"] == "climb" ||
                msg["activity_type"] == "weight" ||
                msg["activity_type"] == "workout") {
                $("#distance").hide();
                $("#pace").hide();
                $("#avg").hide();
                $("#distance-label").hide();
                $("#pace-label").hide();
                $("#avg-label").hide();
                $("#splits").hide();
                $("#activity_graph").hide();
                $("#activity_map").hide();
                $("#activity_splits").hide();
                return 0;
            }
            if  (msg["activity_type"] == "run" ||
                 msg["activity_type"] == "hike") {
                    $("#shoe").html("Shoes: " + msg["shoe"]);
                 }
            get_splits(activity_id);
            $("#distance").html(msg["activity_distance"] + "<span class='text-muted'> miles</span>");
            $("#pace").html(seconds_to_formatted_string(msg["activity_pace_per_mile"]) + "<span class='text-muted'>/mi</span>");
            get_average_pace(-1, $(".card-header img").attr("alt"), "#avg");

            if (typeof(points[0]) != 'undefined') {
                elevations = [];
                times = [];
                coords = [];
                for (var i = 0; i < points.length; i++) {

                    elevations.push(points[i][0]["elevation"][0] * 3.28084);
                    time_in_activity = new Date(points[i][1]["time"][0]);
                    times.push(seconds_to_formatted_string((time_in_activity - activity_date) / 1000));
                    coords.push([parseFloat(points[i][2]["lat"][0]), parseFloat(points[i][3]["lon"][0])]);
                }

                load_map(coords);

                var max_min = get_max_min_elevation(elevations);

                var ctx = $('#activity_graph');
                var myChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: times,
                        datasets: [{
                            data: elevations,
                            borderWidth: 1,
                            pointBorderWidth: 0,
                            pointRadius: 1
                        }]
                    },
                    options: {
                        scales: {
                            yAxes: [{
                                ticks: {
                                    suggestedMin: max_min[1] * 0.9,
                                    max: max_min[0] * (1 + 1 / (max_min[0] / 200))
                                },
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Elevation (ft)'
                                }
                            }],
                            xAxes: [{
                                scaleLabel: {
                                    display: true,
                                    labelString: "Duration"
                                }
                            }]
                        },
                        legend: {
                            display: false
                        }
                    }
                });
            }
        });
}


function get_splits(activity_id) {
    $.get('proc.php', {
            f: 'get_splits',
            id: activity_id
        })
        .done(function(msg) {
            $("#splits").html("<tr><th>Mile</th><th>Pace</th></tr>");
            $("#splits").append("<tbody>");
            for (key of Object.keys(msg)) {
                $("#splits").append("<tr><td>" + msg[key]["mile_count"] + "</td><td>" +
                    seconds_to_formatted_string(msg[key]["mile_pace"]) + "</td</tr>");
            }
            $("#splits").append("</tbody>");
        });
}

function get_activities(num, offset) {
    $.get('proc.php', {
            f: 'get_activities',
            n: num,
            o: offset
        })
        .done(function(msg) {

            //$("#feed").empty();
            for (key of Object.keys(msg)) {
                var activity_start = new Date(msg[key]["start"]);
                var duration = seconds_to_formatted_string(parseInt(msg[key]["duration"]));
                var activity_card = "<div class='card shadow-sm m-1 type-all type-" + msg[key]["type"] + "' style='width: 40rem;'>\n" +
                    "<div class='card-body'>\n" +
                    "<h5 class='card-title border-bottom'>" +
                    "<img style='height: 23px; width: 23px;' src='icons/" +
                    msg[key]["type"] + ".svg' alt='" + msg[key]["type"] +
                    "'>&nbsp;&nbsp;<a  class='text-reset text-decoration-none' href='activity.html?" +
                    msg[key]["id"] + "'>" +
                    msg[key]["name"] + " <span class='text-muted'> - " + activity_start.toLocaleDateString() + "</span>" +
                    " </a><a href='proc.php?f=delete_activity&id=" +
                    msg[key]["id"] + "'>" +
                    "<img class='float-right' src='icons/trash.svg' style='height: 12px; width: 12;'></a></h5> \n ";

                if (msg[key]["type"] != "yoga" && msg[key]["type"] != "climb" && msg[key]["type"] != "workout" && msg[key]["type"] != "weight") {
                    $("#feed").append(activity_card +
                        "<p class='card-text'>" +
                        "<h6>" + msg[key]["distance"] + " " +
                        units + "<span class='muted-text'> in </span>" + duration + "</h6>\n" +
                        msg[key]["description"] +
                        "</p>\n</div>\n</div>\n");
                } else {
                    $("#feed").append(activity_card +
                        "<p class='card-text'>" +
                        "<h6>" + duration + "</h6>\n" +
                        msg[key]["description"] +
                        "</p>\n</div>\n</div>\n");
                }

            }

        });
}

function load_map(activity_points) {
    var map = L.map('activity_map')
    /*.setView(
                [38.8221520, -76.7638780], 13)*/
    ;
    L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        subdomains: ['a', 'b', 'c']
    }).addTo(map);

    var activity_drawing = L.polyline(activity_points).addTo(map);
    map.fitBounds(activity_drawing.getBounds());
}

function load_add_dialog() {
    $("#add-activity-dialog").load("add.html");

    $.get('proc.php', {
            f: 'list_shoes',
            active: 1
        })
        .done(function(msg) {
            $("#shoes").empty();
            var opts = "";
            for (key of Object.keys(msg)) {
                opts += `<option value='${msg[key]["id"]}'>${msg[key]["name"]}(${msg[key]["brand"]} ${msg[key]["model"]})</option>\\n`;
            }
            $("#shoes").html(opts);
        });
}

function list_shoes() {
    $.get('proc.php', {
            f: 'list_shoes',
            active: 1
        })
        .done(function(msg) {
            for (key of Object.keys(msg)) {
                $("#shoe_stats").append(`<tr><th scope='row' class='pl-2 pr-0 ml-0 mr-0' style='font-size: 12pt;'>${msg[key]["name"]}</th><td class='text-muted pl-0 pr-0 ml-0 mr-0' style='font-size: 10pt;'>${msg[key]["brand"]} ${msg[key]["model"]}</td><td class='pl-0 pr-0 ml-0 mr-0' style='font-size: 12pt;'>${parseFloat(msg[key]["miles"]).toFixed(1)} mi</td><td class='pl-0 pr-2 ml-0 mr-0'><a href='proc.php?f=delete_shoe&id=${msg[key]["id"]}'><img class='float-right' src='icons/trash.svg' style='height: 12px; width: 12px;'></a></td></tr>`);
            }
        });
}

function get_average_pace(dist = -1.00, activity_type = $(".card-header img").attr("alt"), target) {
    year = new Date();
    avg = 0.00;
    if (dist == -1.00) {
        dist = parseFloat($("#distance").html());
    }
    year = year.getFullYear();
    $.get('proc.php', {
            f: 'get_activities_by_length',
            min: dist * 0.9,
            max: dist * 1.1,
            type: activity_type,
            start: year.toString() + "-01-01 00:01:01"
        })
        .done(function(msg) {
            denom = 0;
            for (denom; denom < msg.length; denom++) {
                avg = avg + parseFloat(msg[denom]["pace"]);
            }
            if (denom > 0) {
                avg = avg / denom;
            } else {
                avg = 0;
            }


            $(target).html(seconds_to_formatted_string(avg) + "<span class='text-muted'>/mi</span>");
        });
}

$(document).on("change", "#activity_type", function() {
    if ($("#activity_type").val() != "run" && $("#activity_type").val() != "hike") {
        $("#shoes").prop("disabled", true);
        $("#shoes").val("-1");
    } else {
        $("#shoes").prop("disabled", false);
    }
});
