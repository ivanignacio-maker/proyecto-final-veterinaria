<?php
    $pageTitle = 'Términos de Servicio para Veterinarias';
    $pageDescription = 'Políticas de uso, custodia de historias clínicas, privacidad médica y normativas de seguridad para centros veterinarios y profesionales en VetCompartido.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm">
    <div class="public-container">
        <div class="page-intro-header">
            <span class="section-eyebrow">Marco Legal y Normativo</span>
            <h1>Términos de Servicio para Veterinarias</h1>
            <p>
                Transparencia, custodia ética de datos clínicos y seguridad de nivel institucional para centros veterinarios, hospitales y profesionales de la salud animal.
            </p>
        </div>

        <div class="legal-layout-grid">
            <!-- Sidebar Fijo de Navegación Rápida -->
            <aside class="legal-sidebar-sticky">
                <h4 class="legal-nav-title">Contenido del Acuerdo</h4>
                <ul class="legal-nav-list">
                    <li><a href="#definiciones">1. Definiciones y Alcance</a></li>
                    <li><a href="#custodia">2. Custodia de Historias Clínicas</a></li>
                    <li><a href="#privacidad">3. Privacidad y Seguridad de Datos</a></li>
                    <li><a href="#migracion-legal">4. Migración Asistida de Bases de Datos</a></li>
                    <li><a href="#sla">5. Disponibilidad y Respaldos (SLA)</a></li>
                    <li><a href="#responsabilidad">6. Responsabilidad Médica Profesional</a></li>
                    <li><a href="#portabilidad">7. Portabilidad y Baja del Servicio</a></li>
                    <li><a href="#contacto-legal">8. Contacto Legal y Regulatorio</a></li>
                </ul>
            </aside>

            <!-- Contenido Detallado de Términos -->
            <article class="legal-article-card">
                <!-- Sección 1 -->
                <section class="legal-section-block" id="definiciones">
                    <h3>1. Definiciones y Ámbito de Aplicación</h3>
                    <p>
                        El presente acuerdo regula los términos y condiciones de contratación y uso de la plataforma SaaS <strong>VetCompartido</strong> por parte de personas humanas o jurídicas legalmente constituidas como veterinarias, hospitales veterinarios, consultorios o profesionales veterinarios matriculados (en adelante, <em>"El Centro Veterinario"</em> o <em>"La Veterinaria"</em>).
                    </p>
                    <p>
                        La utilización de la plataforma implica el pleno conocimiento y aceptación sin reservas de cada una de las cláusulas aquí descriptas, garantizando un marco de operación profesional, seguro y alineado a los códigos deontológicos de la medicina veterinaria.
                    </p>
                </section>

                <!-- Sección 2 -->
                <section class="legal-section-block" id="custodia">
                    <h3>2. Titularidad, Propiedad y Custodia de las Historias Clínicas</h3>
                    <p>
                        <strong>Propiedad Exclusiva:</strong> La Veterinaria es y seguirá siendo en todo momento la titular y responsable legal exclusiva de la información médica, diagnósticos, recetas, planes de vacunación, estudios complementarios y registros clínicos incorporados en la plataforma.
                    </p>
                    <div class="legal-notice-highlight">
                        <strong>Principio Rector de VetCompartido:</strong> Bajo ninguna circunstancia VetCompartido comercializa, cede, arrienda o utiliza los datos de tus pacientes o tutores para fines comerciales, publicitarios o de venta cruzada a terceros.
                    </div>
                    <p>
                        <strong>Rol de la Plataforma:</strong> VetCompartido actúa estrictamente en calidad de encargado de tratamiento tecnológico y facilitador de custodia informática, proveyendo la infraestructura de almacenamiento en la nube, alta disponibilidad e interfaces de consulta.
                    </p>
                    <p>
                        <strong>Acceso de Tutores:</strong> Los tutores o propietarios de mascotas registrados tienen habilitado un acceso de carácter exclusivamente informativo a la libreta sanitaria digital de sus animales a cargo. Los tutores no pueden editar, suprimir ni alterar ninguna anotación clínica generada por el personal médico.
                    </p>
                </section>

                <!-- Sección 3 -->
                <section class="legal-section-block" id="privacidad">
                    <h3>3. Privacidad, Manejo Seguro y Cifrado de Datos</h3>
                    <p>
                        En estricto cumplimiento con la legislación de Protección de Datos Personales (Ley N° 25.326 y estándares internacionales compatibles con GDPR/HIPAA aplicables a sanidad), VetCompartido implementa:
                    </p>
                    <ul>
                        <li><strong>Cifrado de Punto a Punto:</strong> Transmisión de datos protegida mediante certificados TLS 1.3 con cifrado AES de 256 bits.</li>
                        <li><strong>Aislamiento de Bases de Datos:</strong> Mecanismos de seguridad lógicos que garantizan la estricta segregación y confidencialidad entre diferentes centros veterinarios.</li>
                        <li><strong>Trazabilidad y Auditoría:</strong> Registro inmutable (log de actividad) de cada acción médica, modificación de stock o consulta de expediente, identificando usuario, fecha y hora.</li>
                        <li><strong>Secreto Profesional:</strong> Todo el personal técnico y de soporte de VetCompartido suscribe convenios vinculantes de confidencialidad y no divulgación.</li>
                    </ul>
                </section>

                <!-- Sección 4 -->
                <section class="legal-section-block" id="migracion-legal">
                    <h3>4. Políticas del Servicio de Migración Asistida</h3>
                    <p>
                        El servicio de <strong>Migración Asistida de Pacientes</strong> es un beneficio orientado a eliminar barreras de entrada para las veterinarias:
                    </p>
                    <ul>
                        <li><strong>Autorización de Tratamiento:</strong> Al suministrar archivos de bases de datos previas (Excel, CSV, backups relacionales o similares), La Veterinaria autoriza a los ingenieros de VetCompartido a procesar, estructurar y cargar dichos registros en su cuenta asignada.</li>
                        <li><strong>Destrucción Segura de Archivos Temporales:</strong> Una vez completada y validada la importación por parte del Centro Veterinario, los archivos crudos de migración alojados temporalmente en el entorno de staging son destruidos de manera segura e irreversible en un plazo máximo de 7 días corridos.</li>
                        <li><strong>Garantía de Integridad:</strong> VetCompartido realiza validaciones de consistencia lógica para evitar duplicaciones o inconsistencias en los números de documento o fichas de pacientes.</li>
                    </ul>
                </section>

                <!-- Sección 5 -->
                <section class="legal-section-block" id="sla">
                    <h3>5. Disponibilidad del Servicio (SLA) y Respaldos</h3>
                    <p>
                        VetCompartido asume el compromiso de brindar un Acuerdo de Nivel de Servicio (SLA) con un <strong>99.9% de uptime mensual</strong> garantizado, exceptuando ventanas de mantenimiento programadas comunicadas con un mínimo de 48 horas de anticipación en horarios de baja demanda operativa.
                    </p>
                    <p>
                        Se realizan copias de seguridad (backups) automáticas con redundancia geográfica cada 24 horas, asegurando la continuidad del negocio ante cualquier contingencia técnica mayor.
                    </p>
                </section>

                <!-- Sección 6 -->
                <section class="legal-section-block" id="responsabilidad">
                    <h3>6. Responsabilidad Médica y Farmacológica</h3>
                    <p>
                        VetCompartido es una herramienta informática de soporte a la gestión y no interviene ni asume responsabilidad alguna por:
                    </p>
                    <ul>
                        <li>Los actos médicos veterinarios, diagnósticos, tratamientos o prescripciones farmacológicas emitidas por los profesionales tratantes.</li>
                        <li>La veracidad o exactitud de los datos volcados por el personal de la clínica.</li>
                        <li>El control físico y la administración real del inventario de medicamentos estupefacientes o psicotrópicos sujetos a normativas sanitarias locales (SENASA / ANMAT u organismos provinciales afines).</li>
                    </ul>
                </section>

                <!-- Sección 7 -->
                <section class="legal-section-block" id="portabilidad">
                    <h3>7. Portabilidad y Baja del Servicio (Cero Retención Forzada)</h3>
                    <p>
                        Creemos en relaciones basadas en la calidad y confianza, no en el cautiverio digital. En caso de solicitar la baja del servicio:
                    </p>
                    <ul>
                        <li>La Veterinaria puede solicitar en cualquier momento la exportación íntegra de sus pacientes, historias clínicas, fichas de tutores y stock en formatos interoperables (CSV/JSON/PDF) sin penalidad comercial.</li>
                        <li>Una vez entregada la exportación y finalizado el período contratado, los datos son conservados en estado pasivo durante 30 días adicionales para contingencias antes de su borrado definitivo y seguro.</li>
                    </ul>
                </section>

                <!-- Sección 8 -->
                <section class="legal-section-block" id="contacto-legal">
                    <h3>8. Contacto Legal y Notificaciones</h3>
                    <p>
                        Para consultas relacionadas con estos Términos, acuerdos de procesamiento de datos (DPA) específicos o requerimientos judiciales, podés comunicarte con nuestro departamento legal:
                    </p>
                    <p>
                        📧 <strong>Correo oficial:</strong> legal@vetcompartido.com<br>
                        🏢 <strong>Dirección de notificaciones:</strong> Área Legal &amp; Compliance VetCompartido, Buenos Aires, Argentina.<br>
                        ⏱ <strong>Última actualización:</strong> Octubre 2026.
                    </p>
                </section>
            </article>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
