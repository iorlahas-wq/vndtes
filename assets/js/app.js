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

/*=========================================
SHOW / HIDE PASSWORD
=========================================*/

const togglePassword=document.getElementById("togglePassword");

const password=document.getElementById("password");

if(togglePassword){

togglePassword.addEventListener("click",function(){

if(password.type==="password"){

password.type="text";

this.innerHTML='<i class="bi bi-eye-slash"></i>';

}else{

password.type="password";

this.innerHTML='<i class="bi bi-eye"></i>';

}

});

}

/*=========================================
LOGIN BUTTON LOADER
=========================================*/

const loginForm=document.querySelector("form");

if(loginForm){

loginForm.addEventListener("submit",function(){

const btn=document.getElementById("loginButton");

const txt=document.getElementById("loginText");

const loading=document.getElementById("loginLoading");

if(btn){

btn.disabled=true;

txt.classList.add("d-none");

loading.classList.remove("d-none");

}

});

}