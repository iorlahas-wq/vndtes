document.addEventListener("DOMContentLoaded", function () {

    /* ===============================
       SIDEBAR
    ================================ */

    const toggle = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("sidebar");
    const content = document.querySelector(".content-wrapper");
    const footer = document.querySelector(".footer");

    if (toggle) {

        toggle.addEventListener("click", function () {

            if (window.innerWidth < 992) {

                sidebar.classList.toggle("mobile");

            } else {

                sidebar.classList.toggle("collapsed");

                content.classList.toggle("expanded");

                footer.classList.toggle("expanded");

            }

        });

    }

    /* ===============================
       NETWORK STATUS ROTATOR
    ================================ */

    const messages = [

        "✔ Router responding normally",

        "✔ Switch operational",

        "✔ Server online",

        "✔ DHCP service active",

        "✔ DNS responding",

        "✔ Network healthy"

    ];

    let i = 0;

    const status = document.getElementById("networkMessage");

    if(status){

        status.innerHTML = messages[0];

        setInterval(function(){

            i++;

            if(i>=messages.length){

                i=0;

            }

            status.innerHTML = messages[i];

        },3000);

    }

});