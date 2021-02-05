var months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
var monthly_miles = new Array(12).fill(0.0);
var year_graph;

function monthly_totals(activity_types) {
    monthly_miles.fill(0.0);
    cur_year = new Date;
    cur_year = cur_year.getFullYear();
    cur_month = new Date;
    cur_month = cur_month.getMonth() + 1;

    cur_month = (cur_month < 10 ? cur_month.toString().padStart(2, "0") : cur_month.toString());
    int_d = new Date(cur_year, cur_month, 1);
    d = new Date(int_d - 1);
    d = d.getDate();
    start = cur_year + "-01-01 00:00:00Z";
    end = cur_year + "-" + cur_month + "-" + d + " 23:59:59Z";
    $.get('proc.php', {
            f: 'get_miles_between_dates',
            start: start,
            end: end,
            type: activity_types
        })
        .done(function(msg) {

            for (result of Object.keys(msg)) {
                event_month = parseInt(new Date(msg[result][1].split(" ")[0]).getUTCMonth());
                monthly_miles[event_month] += parseFloat(msg[result][0]);
            }
            for (i = 0; i < 12; i++) {
                monthly_miles[i] = monthly_miles[i].toFixed(1);
            }
            populate_year();
        });

}

function populate_year() {
    var ctx = $('#year_by_month');
    $('#year_by_month').empty();
    var chart_data = {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Monthly Miles',
                data: monthly_miles,
                backgroundColor: "rgb(3, 173, 255)"
            }]
        },
        options: {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]

            },
        }
    };

    if (typeof(year_graph) != 'undefined') {
        year_graph.destroy();
    }
    year_graph = new Chart(ctx, chart_data);
}