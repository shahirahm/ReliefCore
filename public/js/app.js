function showAlert(message, type="success"){
  const alert = document.getElementById("appAlert");
  if(!alert) return;
  alert.textContent = message;
  alert.className = "alert show " + (type === "error" ? "error" : "success");
  setTimeout(()=> alert.className = "alert", 2800);
}
function openModal(id){
  const m = document.getElementById(id);
  if(m) m.classList.add("show");
}
function closeModal(id){
  const m = document.getElementById(id);
  if(m) m.classList.remove("show");
}
function fakeSubmit(event, message="Saved successfully."){
  event.preventDefault();
  showAlert(message, "success");
}
function fakeError(event, message="Action failed. Please check required fields."){
  event.preventDefault();
  showAlert(message, "error");
}

function showSection(sectionId, clickedLink){
  const dashboard = clickedLink.closest(".dashboard");
  if(!dashboard) return;

  const sections = dashboard.querySelectorAll(".tab-section");
  sections.forEach(section => section.classList.remove("active"));

  const target = document.getElementById(sectionId);
  if(target) target.classList.add("active");

  const links = dashboard.querySelectorAll(".sidebar a");
  links.forEach(link => link.classList.remove("active"));
  clickedLink.classList.add("active");
}
