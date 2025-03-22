<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Query Builder - Manage Database</title>

    <!-- Import Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Import Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Import Toastr for notifications -->
    <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">

    <!-- Import DataTables CSS for table formatting -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.css" />

    <!-- Import Tabulator CSS for advanced table handling -->
    {{-- <link href="https://unpkg.com/tabulator-tables/dist/css/tabulator.min.css" rel="stylesheet"> --}}
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">

    <!-- CSRF Token for security in AJAX requests -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Base site URL for JavaScript usage -->
    <meta name="site-url" content="{{ url('/') }}">

    <script type="text/javascript">
        var site_url = '{{ url('/') }}'; // Store base URL in JavaScript variable
    </script> 

    <style>
        /* Add padding to body to prevent overlap with the navbar */
        body {
            padding-top: 56px;
        }


         /* -------------- Loader animation start --------------------- */
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
         /* -------------- Loader animation end --------------------- */

         /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .sidebar {
                top: 5rem;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>

    @yield('css') <!-- Placeholder for additional styles from child templates -->

</head>
<body>
    
    
    @yield('content') <!-- Placeholder for page-specific content -->

    <!-- Import jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Import Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <!-- Import Toastr JS for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/toastr.min.js"></script>

    <!-- Import DataTables JS for table interactions -->
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>

    <!-- Import Tabulator for table management -->
    {{-- <script type="text/javascript" src="https://unpkg.com/tabulator-tables/dist/js/tabulator.min.js"></script> --}}
    <script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>

    <!-- Import Sweetalert2 for showing some messages -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="text/javascript">

         // Configure Toastr notifications
        toastr.options.closeButton = true;
        
        document.addEventListener("DOMContentLoaded", function(){
            document.querySelectorAll('.sidebar .nav-link').forEach(function(element){

                element.addEventListener('click', function (e) {

                    let nextEl = element.nextElementSibling;
                    let parentEl  = element.parentElement;    

                    if(nextEl) {
                        e.preventDefault(); 
                        let mycollapse = new bootstrap.Collapse(nextEl);

                        if(nextEl.classList.contains('show')){
                            mycollapse.hide();
                        } else {
                            mycollapse.show();

                            var opened_submenu = parentEl.parentElement.querySelector('.submenu.show');

                            if(opened_submenu){
                                new bootstrap.Collapse(opened_submenu);
                            }
                        }
                    }
                }); 
            }) 
        }); 

        $(document).ready(function() {
        // Add any document-ready logic here if needed
        });

        function showSweetalert(status='', title='', text='', btn='OK') {
            Swal.fire({
                icon: status,
                title: title,
                text: text,
                confirmButtonText: btn
            });
        }

    </script>
    
    @yield('scripts')  <!-- Placeholder for additional scripts from child templates -->
 
</body>
</html>