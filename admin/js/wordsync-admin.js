/*! bravewordsync 2017-04-25 */
!function(a) {
    var b = !1;
    if ("function" == typeof define && define.amd && (define(a), b = !0), "object" == typeof exports && (module.exports = a(), 
    b = !0), !b) {
        var c = window.Cookies, d = window.Cookies = a();
        d.noConflict = function() {
            return window.Cookies = c, d;
        };
    }
}(function() {
    function a() {
        for (var a = 0, b = {}; a < arguments.length; a++) {
            var c = arguments[a];
            for (var d in c) b[d] = c[d];
        }
        return b;
    }
    function b(c) {
        function d(b, e, f) {
            var g;
            if ("undefined" != typeof document) {
                if (arguments.length > 1) {
                    if (f = a({
                        path: "/"
                    }, d.defaults, f), "number" == typeof f.expires) {
                        var h = new Date();
                        h.setMilliseconds(h.getMilliseconds() + 864e5 * f.expires), f.expires = h;
                    }
                    try {
                        g = JSON.stringify(e), /^[\{\[]/.test(g) && (e = g);
                    } catch (a) {}
                    return e = c.write ? c.write(e, b) : encodeURIComponent(String(e)).replace(/%(23|24|26|2B|3A|3C|3E|3D|2F|3F|40|5B|5D|5E|60|7B|7D|7C)/g, decodeURIComponent), 
                    b = encodeURIComponent(String(b)), b = b.replace(/%(23|24|26|2B|5E|60|7C)/g, decodeURIComponent), 
                    b = b.replace(/[\(\)]/g, escape), document.cookie = [ b, "=", e, f.expires ? "; expires=" + f.expires.toUTCString() : "", f.path ? "; path=" + f.path : "", f.domain ? "; domain=" + f.domain : "", f.secure ? "; secure" : "" ].join("");
                }
                b || (g = {});
                for (var i = document.cookie ? document.cookie.split("; ") : [], j = /(%[0-9A-Z]{2})+/g, k = 0; k < i.length; k++) {
                    var l = i[k].split("="), m = l.slice(1).join("=");
                    '"' === m.charAt(0) && (m = m.slice(1, -1));
                    try {
                        var n = l[0].replace(j, decodeURIComponent);
                        if (m = c.read ? c.read(m, n) : c(m, n) || m.replace(j, decodeURIComponent), this.json) try {
                            m = JSON.parse(m);
                        } catch (a) {}
                        if (b === n) {
                            g = m;
                            break;
                        }
                        b || (g[n] = m);
                    } catch (a) {}
                }
                return g;
            }
        }
        return d.set = d, d.get = function(a) {
            return d.call(d, a);
        }, d.getJSON = function() {
            return d.apply({
                json: !0
            }, [].slice.call(arguments));
        }, d.defaults = {}, d.remove = function(b, c) {
            d(b, "", a(c, {
                expires: -1
            }));
        }, d.withConverter = b, d;
    }
    return b(function() {});
}), function(a) {
    "use strict";
    function b(a) {
        switch (a) {
          case s:
            return "Idle";

          case t:
            return "Waiting...";

          case u:
            return "Gathering Data...";

          case v:
            return "Reviewing Data";

          case w:
            return "Applying Changes...";

          case x:
            return "Done!";

          default:
            return "Unknown State";
        }
    }
    function c(a) {
        switch (a) {
          case y:
            return "Waiting";

          case z:
            return "Processing";

          case A:
            return "Done Processing";

          case B:
            return "Updating";

          case C:
            return "Done Updating";

          default:
            return "Unknown State";
        }
    }
    function d(b, c, d, e) {
        a.ajax({
            dataType: "json",
            method: "POST",
            url: ajaxurl,
            data: {
                action: "wordsync_admin",
                command: b,
                data: c
            },
            success: d
        }).fail(e);
    }
    function e() {
        d("getlog", {}, function(b) {
            b.hasOwnProperty("log") && a(".log").val(b.log);
        });
    }
    function f(b, c) {
        b && a("#progress").html(b), a(".statusbox").toggleClass("hidden", 0 == window.bravesyncjob.id && window.bravesyncjob.status != x), 
        c ? a(".error-message").removeClass("hidden").find(".error-text").html(c) : a(".error-message").addClass("hidden"), 
        a(".resultsbox, .btn-proceed").toggleClass("hidden", window.bravesyncjob.status != v), 
        a(".btn-cancelsync").toggleClass("hidden", 0 == window.bravesyncjob.id), a(".btn-sync").toggleClass("hidden", 0 != window.bravesyncjob.id);
    }
    function g(a, b, c, d) {
        var e = '<div class="changegroup ' + ("" == d ? "collapsed" : "") + '" data-processor="' + a + '"><div class="changeheading"><h3>' + b + ' <span class="small">' + c + '</span></h3><a href="#" class="downarrow"><span class="dashicons dashicons-arrow-down"></span></a></div><div class="changecontent"><table class="wp-list-table widefat fixed striped table-changes"><thead><tr><td class="check-column"><input class="checkall" type="checkbox" data-processor="' + a + '" checked/></td><th class="small-column">#</th><th>Name</th><th>Field</th><th class="data-column">Local Value</th><th class="small-column"></th><th class="data-column">Remote Value</th></tr></thead><tbody>' + d + "</tbody></table></div></div>";
        return e;
    }
    function h(a, b, c, d, e, f, g) {
        var h, i;
        switch (g) {
          case F:
            h = "create-mark dashicons-arrow-left-alt", i = "Create";
            break;

          case E:
            h = "delete-mark dashicons-no", i = "Remove";
            break;

          case D:
            h = "edit-mark dashicons-arrow-left-alt", i = "Update";
        }
        var j = '<tr><td><input type="checkbox" class="checkitem" checked name="' + a + '[]" value="' + b + '"/></td><td>' + b + "</td><td>" + c + "</td><td>" + d + '</td><td class="data-cell">' + ("undefined" != typeof e ? e : "-") + '</td><td><span class="dashicons ' + h + '" title="' + i + '"></span></td><td class="data-cell">' + ("undefined" != typeof f ? f : "-") + "</td></tr>";
        return j;
    }
    function i(a, b, c, d, e) {
        var f, g;
        switch (e) {
          case F:
            f = "create-mark dashicons-arrow-left-alt", g = "Create";
            break;

          case E:
            f = "delete-mark dashicons-no", g = "Remove";
            break;

          case D:
            f = "edit-mark dashicons-arrow-left-alt", g = "Update";
        }
        var h = '<tr><td colspan="2"></td><td>' + a + "</td><td>" + b + '</td><td class="data-cell">' + ("undefined" != typeof c ? c : "-") + '</td><td><span class="dashicons ' + f + '" title="' + g + '"></span></td><td class="data-cell">' + ("undefined" != typeof d ? d : "-") + "</td></tr>";
        return h;
    }
    function j(a) {
        setTimeout(function() {
            q(b(window.bravesyncjob.status));
        }, a);
    }
    function k(c) {
        if (!c || !c.success) return void f(!1, c.msg);
        switch (f(b(c.status), !1), c.status != v && a(".changeslist").html(""), c.status) {
          case v:
            if (c.hasOwnProperty("changes")) {
                window.bravesyncjob.changes = c.changes;
                for (var d = c.changes, e = "", k = 0; k < d.length; k++) {
                    for (var l = "", n = d[k].changes, o = 0; o < n.length; o++) if (n[o].differences.length > 0) if (1 == n[o].differences.length) {
                        var p = n[o].differences[0];
                        l += h(d[k].slug, n[o].id, n[o].lname ? n[o].lname : n[o].rname, p.fn + ("" != p.k ? " > " + p.k : ""), p ? p.l : void 0, p ? p.r : void 0, n[o].action);
                    } else {
                        var q = n[o].differences.length + " Differences", r = q;
                        n[o].action == E && (q = "Object", r = "Doesnt Exist"), n[o].action == F && (q = "Doesnt Exist", 
                        r = "Object"), l += h(d[k].slug, n[o].id, n[o].lname ? n[o].lname : n[o].rname, "", q, r, n[o].action);
                        for (var s = 0; s < n[o].differences.length; s++) {
                            var p = n[o].differences[s];
                            l += i("", p.fn + ("" != p.k ? " > " + p.k : ""), p && p.hasOwnProperty("l") ? p.l : void 0, p && p.hasOwnProperty("r") ? p.r : void 0, n[o].action);
                        }
                    } else {
                        var q = void 0, r = void 0;
                        n[o].action == E && (q = "Object", r = "Doesn't Exist"), n[o].action == F && (q = "Doesn't Exist", 
                        r = "Object"), l += h(d[k].slug, n[o].id, n[o].lname ? n[o].lname : n[o].rname, "", q, r, n[o].action);
                    }
                    e += g(d[k].slug, d[k].name, "(" + n.length + " changes)", l);
                }
                a(".changeslist").html(e);
            }
            break;

          case x:
            m(), f("Done!");
            break;

          default:
            j(10);
        }
    }
    function l(d) {
        window.bravesyncjob = window.bravesyncjob || {}, d.hasOwnProperty("id") && (window.bravesyncjob.id = d.id), 
        window.bravesyncjob.status = d.status ? d.status : u, d.hasOwnProperty("processors") && (window.bravesyncjob.processors = d.processors), 
        console.log("Job Status is now " + b(window.bravesyncjob.status));
        for (var e = 0; e < window.bravesyncjob.processors.length; e++) a('.processor[data-proc="' + window.bravesyncjob.processors[e].slug + '"] .status').html(c(window.bravesyncjob.processors[e].status));
        if (0 != window.bravesyncjob.id && "newjob" != window.bravesyncjob.id) {
            for (var f = [ u, v, w, x ], g = 0, h = 1 / f.length, e = 0; e < f.length; e++) if (g += h, 
            f[e] == window.bravesyncjob.status) {
                var i;
                if (window.bravesyncjob.status == u && (i = A), window.bravesyncjob.status == w && (i = C), 
                i) for (var j = 1 / window.bravesyncjob.processors.length * h, e = 0; e < window.bravesyncjob.processors.length; e++) window.bravesyncjob.processors[e].status == i && (g += j);
                break;
            }
            g = Math.round(100 * g), a(".progressbar .bar").css("width", g + "%"), a(".progressbar .percent").html(g + "%");
        } else window.bravesyncjob.status == x ? (a(".progressbar .bar").css("width", "100%"), 
        a(".progressbar .percent").html("100%")) : (a(".progressbar .bar").css("width", "0%"), 
        a(".progressbar .percent").html("0%"));
        Cookies.set("bravewordsync_currentjob", window.bravesyncjob, {
            expires: 1,
            path: ""
        });
    }
    function m() {
        window.bravesyncjob = {
            id: 0,
            status: s,
            processors: []
        }, Cookies.remove("bravewordsync_currentjob");
    }
    function n() {
        var a = Cookies.getJSON("bravewordsync_currentjob");
        return null != a && "object" == typeof a ? (l(a), console.log("Loaded JOB status from cookie: ", a), 
        !0) : (m(), !1);
    }
    function o(a) {
        0 != window.bravesyncjob.id && (d("canceljob", {
            jobid: window.bravesyncjob.id
        }, function(a) {
            console.log("Deleted job!", a);
        }, function() {}), l({
            id: 0,
            status: s,
            processors: []
        })), f(!1, a);
    }
    function p() {
        f(!1, !1), n() && window.bravesyncjob.status == v && (f(b(window.bravesyncjob.status), !1), 
        d("getchanges", {
            jobid: window.bravesyncjob.id
        }, function(a) {
            a.hasOwnProperty("success") && a.success ? (a.status = v, k(a)) : o(a && a.hasOwnProperty("success") ? a.msg : "Recieved an invalid response back from the server."), 
            e();
        }, function() {
            f("", "Unable to retrieve changes list from the server. Did you lose connection? Please try again.");
        }));
    }
    function q(a) {
        if (f(a, !1), 0 != window.bravesyncjob.id) {
            var b = {
                jobid: window.bravesyncjob.id
            };
            window.bravesyncjob.status == v && (b.selects = r()), d("continuesync", b, function(a) {
                a.hasOwnProperty("success") && 1 == a.success ? (l(a), k(a)) : o(a && a.hasOwnProperty("msg") ? a.msg : "The server sent an invalid response."), 
                e();
            }, function(a) {
                f(!1, "Oops! Something went wrong!"), o();
            });
        }
    }
    function r() {
        for (var b = {}, c = 0; c < window.bravesyncjob.processors.length; c++) {
            var d = [], e = window.bravesyncjob.processors[c].slug;
            a('.changegroup[data-processor="' + e + '"] .checkitem:checked').each(function(b) {
                d.push(a(this).val());
            }), b[e] = d;
        }
        return console.log("Gathered selected changes. Change list is: ", b), b;
    }
    var s = 0, t = 1, u = 2, v = 3, w = 4, x = 5, y = 0, z = 1, A = 2, B = 3, C = 4, D = "update", E = "remove", F = "create";
    window.bravesyncjob = {
        id: 0,
        status: s,
        processors: []
    }, a(document).ready(function() {
        a(".processors").on("click", ".processor", function(b) {
            var c = a(this), d = c.find("input:checkbox");
            return b.target == d.get(0) || (b.preventDefault(), d.prop("checked", !d.is(":checked")), 
            d.change(), !1);
        }), a(".processor input:checkbox").change(function(b) {
            var c = a(this).parents(".processor");
            a(this).is(":checked") ? c.addClass("selected") : c.removeClass("selected");
        }), a(".processor input:checkbox:checked").each(function(b) {
            a(this).parents(".processor").addClass("selected");
        }), a(".btn-showlog").click(function(b) {
            a(".logbox .inside").toggleClass("hidden");
        }), a(".btn-sync").click(function(b) {
            b.preventDefault(), m(), l({
                id: "newjob",
                status: s
            }), f("Starting Sync Job...", !1);
            var c = [];
            return a('input[name="processor_enabled[]"]:checked').each(function() {
                c.push(a(this).val());
            }), 0 == c.length ? void alert("Please select some data to synchronise.") : void d("startsync", {
                remoteurl: a("#remoteurl").val(),
                processors: c
            }, function(a) {
                a.hasOwnProperty("success") ? 1 == a.success ? (l(a), j(10)) : f(!1, !a.success && a.msg) : f(!1, a && a.hasOwnProperty("success") ? a.msg : "Recieved an invalid response back from the server. Please try refresh the page."), 
                e();
            }, function(a) {
                f(!1, "Oops! Something went wrong!"), console.log(a);
            });
        }), a(".btn-proceed").click(function(a) {
            a.preventDefault(), window.bravesyncjob.status == v ? q("Applying Changes...") : f(!1, "You can't proceed when the job isnt at the REVIEW stage!");
        }), a(".btn-cancelsync").click(function(a) {
            a.preventDefault(), o();
        }), a(".bravewrap").on("click", ".changegroup .downarrow", function(b) {
            b.preventDefault();
            var c = a(this).parents(".changegroup");
            c.hasClass("collapsed") ? (c.removeClass("collapsed"), a(this).find(".dashicons").removeClass("dashicons-arrow-down").addClass("dashicons-arrow-right")) : (c.addClass("collapsed"), 
            a(this).find(".dashicons").addClass("dashicons-arrow-down").removeClass("dashicons-arrow-right"));
        }), a("#changes").on("click", "input.checkall", function(b) {
            var c = a(this).attr("data-processor");
            a('.changegroup[data-processor="' + c + '"] .checkitem').prop("checked", a(this).is(":checked"));
        }), a("#changes").on("change", ".checkitem", function(b) {
            var c = a(this).parentsUntil(".changegroup");
            a(this).is(":checked") ? c.find(".checkall").prop("checked", 0 == c.find(".checkitem:not(:checked)").length) : c.find(".checkall").prop("checked", !1);
        }), p();
    });
}(jQuery);