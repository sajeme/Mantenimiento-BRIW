// script.js
$(document).ready(function() {
  $("#executeCrawler").click(function() {
    $.ajax({
      url: "../Back/crawler.php",
      type: "POST",
      success: function(response) {
        console.log("Crawler ejecutado correctamente.");
        setTimeout(function() {
          window.location.href = "../Front/index.html";
        }, 60000); // 60000 milisegundos = 1 minuto
      },
      error: function(xhr, status, error) {
        console.error("Error al ejecutar el Crawler:", error);
        window.location.href = "../Front/index.html";
      }
    });
  });
});
