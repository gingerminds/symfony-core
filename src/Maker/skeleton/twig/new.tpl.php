{% extends '@GingermindsCore/crud/form.html.twig' %}

{% block form_body %}
    {{ include('<?= $resource->templateDirectory() ?>/_form.html.twig') }}
{% endblock %}
