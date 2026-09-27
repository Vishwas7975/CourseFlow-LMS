<script>
// Sidebar toggle for mobile
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
if(menuToggle && sidebar){
    menuToggle.addEventListener('click', function(){
        sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function(e){
        if(!sidebar.contains(e.target) && !menuToggle.contains(e.target)){
            sidebar.classList.remove('open');
        }
    });
}
</script>
</body>
</html>
