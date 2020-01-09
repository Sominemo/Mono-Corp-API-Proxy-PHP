"use strict";

const appHeight = function () {
    const doc = document.documentElement
    doc.style.setProperty('--app-height', window.innerHeight + "px")
}

window.addEventListener('resize', appHeight)
appHeight()

var blocks = document.querySelector(".blocklist")

Array().slice.call(document.querySelectorAll(".hint:not(.top)"))
    .forEach(function (e) {
        e.addEventListener("click", function () {
            blocks.scrollBy(0, window.innerHeight)
        })
    })

Array().slice.call(document.querySelectorAll(".hint.top"))
    .forEach(function (e) {
        e.addEventListener("click", function () {
            blocks.scrollTo(0, 0)
        })
    })