{# Fields of the <?= $resource->snake ?> form, shared by new.html.twig and edit.html.twig. #}
{% embed '@GingermindsCore/crud/_form_layout.html.twig' %}
    {% block main %}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    {{ form_widget(form) }}
                </div>
            </div>
        </div>
    {% endblock %}
{% endembed %}
