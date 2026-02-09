<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>BRAIN</title>
        <link href="https://cdn.lineicons.com/4.0/lineicons.css" rel="stylesheet"/>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.2/font/bootstrap-icons.css" rel="stylesheet"/>
        <link rel="stylesheet" href="css/plugins/bootstrap.min.css">
        <!-- <link rel="stylesheet" href="css/custom.css"> -->
        <script type="text/javascript" src="js/plugins/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="js/plugins/jquery.min.js"></script>
        <script type="text/javascript" src="js/plugins/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/plugins/sweetalert2@11.js"></script>
        <script type="text/javascript" src="js/login.js"></script>
        <!-- <script type="text/javascript" src="js/custom.js"></script> -->
        <style>
            .card-img-top{
                background: rgb(207,244,252);
                background: radial-gradient(circle, rgba(207,244,252,1) 0%, rgba(43,48,53,1) 0%, rgba(207,226,255,1) 100%);
            }

            .separator {
            display: flex;
            align-items: center;
            text-align: center;
            }

            .separator::before,
            .separator::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid grey!important;
            }

            .separator:not(:empty)::before {
            margin-right: .25em;
            }

            .separator:not(:empty)::after {
            margin-left: .25em;
            }
        </style>
    </head>

    <body>

        <div class="container">
            
            <div class="row">
                <div class="col-lg-12 vh-100 d-flex justify-content-center align-items-center">
                    
                    <div class="card-group shadow-lg">
                    
                        <div class="card border-0">
                            <img src="assets/brain.gif" class="card-img-top" alt="...">
                            <div class="card-body text-center">
                                <h2 class="card-title">BRAIN</h2>
                                <p class="card-text"><span class="fw-bold">B</span>ackend <span class="fw-bold">R</span>esource and <span class="fw-bold">A</span>ssumptions <span class="fw-bold">IN</span>terface</p>
                            </div>
                        </div>

                        <div style="background-color: #f5f5f5;" class="card border-0">
                            <div class="card-body">
                                
                                <h2 class="card-title text-center mt-4 mb-4">Login </h2>
                                <div class="text-center">
                                    <label class="text-secondary">
                                        <small>
                                            Doesn't have an account yet?
                                            <a href="">Signup</a>
                                        </small>
                                    </label>
                                </div>
                                
                                <div class="form-floating mb-4 mt-4">
                                    <input style="background-color: #f5f5f5;" type="email" class="form-control login-username border-0 border-bottom border-black rounded-0" id="" placeholder="">
                                    <label for="floatingInput">Email Address</label>
                                </div>

                                <!-- <div class="text-end">
                                    <a href="">
                                        <small>Forgot Password?</small>
                                    </a>
                                </div> -->

                                <div class="form-floating mt-4">
                                    <input style="background-color: #f5f5f5;" type="password" class="form-control login-password mb-4 border-0 border-bottom border-black rounded-0" id="" placeholder="">
                                    <label for="floatingPassword">Password</label>
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                    <label class="form-check-label" for="defaultCheck1">
                                        Remember Me
                                    </label>
                                </div>

                                <div style="display: none;" class="alert alert-danger not-found-error" role="alert">
                                    <strong>Account Does Not Exists!</strong> Please Try Again.
                                </div>

                                <div class="d-grid gap-2">
                                    <button class="btn btn-info btn-lg text-white btn-login" type="button">
                                        <strong>LOGIN</strong>
                                    </button>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </body>
</html>